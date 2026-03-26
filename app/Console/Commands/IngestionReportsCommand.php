<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

class IngestionReportsCommand extends Command
{
    protected $signature = 'ingest:reports {--limit=10 : Number of recent report files to list}';

    protected $description = 'List recent ingestion report files under reports/ingestion.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $base = base_path('reports/ingestion');

        if (! File::isDirectory($base)) {
            $this->warn('No reports directory found at reports/ingestion yet.');

            return self::SUCCESS;
        }

        $reports = collect(File::allFiles($base))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'run-report'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take($limit)
            ->values();

        if ($reports->isEmpty()) {
            $this->warn('No run-report files found yet.');

            return self::SUCCESS;
        }

        $this->info('Recent ingestion reports:');
        $this->renderReportList($reports);

        return self::SUCCESS;
    }

    private function renderReportList(Collection $reports): void
    {
        foreach ($reports as $file) {
            $relativePath = str_replace(base_path().'/', '', $file->getPathname());
            $this->line(sprintf('- %s (updated: %s)', $relativePath, gmdate('Y-m-d H:i:s', $file->getMTime()).' UTC'));
        }
    }
}
