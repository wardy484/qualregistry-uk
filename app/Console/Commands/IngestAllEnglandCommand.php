<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class IngestAllEnglandCommand extends Command
{
    protected $signature = 'ingest:all-england
                            {--run-date= : Run date in YYYY-MM-DD format (defaults to UTC today)}
                            {--csv-url= : Override schools CSV URL for ingest:institutions-england}
                            {--base-dir= : Base directory for ingest:ofqual outputs (defaults to repository root)}';

    protected $description = 'Run full England ingestion pipeline (institutions + Ofqual) with step summaries.';

    public function handle(): int
    {
        $runDate = $this->option('run-date') ?: gmdate('Y-m-d');
        $start = microtime(true);

        $steps = [
            [
                'label' => 'Institutions (England schools + placeholders)',
                'command' => 'ingest:institutions-england',
                'options' => array_filter([
                    '--run-date' => $runDate,
                    '--csv-url' => $this->option('csv-url'),
                ], fn ($value) => $value !== null && $value !== ''),
            ],
            [
                'label' => 'Ofqual organisations + qualifications',
                'command' => 'ingest:ofqual',
                'options' => array_filter([
                    '--run-date' => $runDate,
                    '--base-dir' => $this->option('base-dir'),
                ], fn ($value) => $value !== null && $value !== ''),
            ],
        ];

        $summary = [];

        foreach ($steps as $index => $step) {
            $stepStart = microtime(true);
            $this->info(sprintf('Step %d/%d: %s', $index + 1, count($steps), $step['label']));

            $exitCode = Artisan::call($step['command'], $step['options']);
            $stepOutput = trim(Artisan::output());
            $durationMs = (int) round((microtime(true) - $stepStart) * 1000);

            if ($stepOutput !== '') {
                $this->line($stepOutput);
            }

            $summary[] = [
                'step' => $step['command'],
                'status' => $exitCode === 0 ? 'ok' : 'failed',
                'exit_code' => $exitCode,
                'duration_ms' => $durationMs,
            ];

            if ($exitCode !== 0) {
                $this->error("Pipeline halted after failed step: {$step['command']} (exit {$exitCode})");
                $this->printSummary($summary, $runDate, $start);

                return self::FAILURE;
            }
        }

        $this->printSummary($summary, $runDate, $start);

        return self::SUCCESS;
    }

    private function printSummary(array $summary, string $runDate, float $start): void
    {
        $this->newLine();
        $this->info('Ingestion summary:');
        foreach ($summary as $row) {
            $icon = $row['status'] === 'ok' ? '✅' : '❌';
            $this->line(sprintf('- %s %s (exit=%d, %dms)', $icon, $row['step'], $row['exit_code'], $row['duration_ms']));
        }

        $this->line(sprintf('Total duration: %dms', (int) round((microtime(true) - $start) * 1000)));

        $latestReports = $this->findLatestReports($runDate);
        if ($latestReports !== []) {
            $this->line('Latest report files:');
            foreach ($latestReports as $report) {
                $this->line('- '.$report);
            }
        }
    }

    private function findLatestReports(string $runDate): array
    {
        $patterns = [
            base_path("reports/ingestion/institutions/{$runDate}/run-report.md"),
            base_path("reports/ingestion/ofqual/{$runDate}/run-report.md"),
            base_path("reports/ingestion/ofqual/{$runDate}/run-report.json"),
        ];

        $results = [];

        foreach ($patterns as $absolutePath) {
            if (File::exists($absolutePath)) {
                $results[] = str_replace(base_path().'/', '', $absolutePath);
            }
        }

        return $results;
    }
}
