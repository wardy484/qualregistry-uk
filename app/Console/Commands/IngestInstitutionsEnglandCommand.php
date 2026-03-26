<?php

namespace App\Console\Commands;

use App\Support\Institutions\EnglandSchoolsIngestor;
use App\Support\Institutions\PlaceholderInstitutionSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

class IngestInstitutionsEnglandCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ingest:institutions-england
                            {--csv-url= : Override schools CSV URL}
                            {--run-date= : Report date (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ingest England institutions (schools now, FE/HE placeholders).';

    public function handle(EnglandSchoolsIngestor $schoolsIngestor): int
    {
        $runDate = $this->option('run-date') ?: Carbon::now()->toDateString();

        try {
            $schools = $schoolsIngestor->ingest($this->option('csv-url'));
        } catch (Throwable $e) {
            $this->error('Schools ingest failed: '.$e->getMessage());
            $this->writeRunReport($runDate, [
                'schools' => ['status' => 'failed', 'error' => $e->getMessage()],
                'colleges' => (new PlaceholderInstitutionSource('Colleges', 'services.institutions.colleges'))->run(),
                'universities' => (new PlaceholderInstitutionSource('Universities', 'services.institutions.universities'))->run(),
            ]);

            return self::FAILURE;
        }

        $colleges = (new PlaceholderInstitutionSource('Colleges', 'services.institutions.colleges'))->run();
        $universities = (new PlaceholderInstitutionSource('Universities', 'services.institutions.universities'))->run();

        $this->info('Schools: inserted='.$schools['inserted'].' updated='.$schools['updated'].' skipped='.$schools['skipped']);
        $this->line('Colleges: '.$colleges['message']);
        $this->line('Universities: '.$universities['message']);

        $reportPath = $this->writeRunReport($runDate, [
            'schools' => array_merge(['status' => 'ok'], $schools),
            'colleges' => $colleges,
            'universities' => $universities,
        ]);

        $this->info('Run report: '.$reportPath);

        return self::SUCCESS;
    }

    private function writeRunReport(string $runDate, array $payload): string
    {
        $dir = base_path("reports/ingestion/institutions/{$runDate}");
        File::ensureDirectoryExists($dir);

        $reportPath = $dir.'/run-report.md';

        $content = implode("\n", [
            '# Institutions Ingestion Run Report',
            '',
            '- Run date: '.$runDate,
            '- Generated at (UTC): '.Carbon::now()->utc()->toIso8601String(),
            '',
            '## Schools (England)',
            '- Status: '.($payload['schools']['status'] ?? 'unknown'),
            '- Inserted: '.($payload['schools']['inserted'] ?? 0),
            '- Updated: '.($payload['schools']['updated'] ?? 0),
            '- Skipped: '.($payload['schools']['skipped'] ?? 0),
            '- Processed: '.($payload['schools']['processed'] ?? 0),
            '- Source URL: '.($payload['schools']['download_url'] ?? 'n/a'),
            isset($payload['schools']['error']) ? '- Error: '.$payload['schools']['error'] : '',
            '',
            '## Colleges (placeholder)',
            '- Status: '.($payload['colleges']['status'] ?? 'skipped'),
            '- Note: '.($payload['colleges']['message'] ?? ''),
            '',
            '## Universities (placeholder)',
            '- Status: '.($payload['universities']['status'] ?? 'skipped'),
            '- Note: '.($payload['universities']['message'] ?? ''),
            '',
            '## TODO',
            '- Wire FE and HE authoritative sources once access + source contract are agreed.',
        ]);

        File::put($reportPath, trim($content)."\n");

        return $reportPath;
    }
}
