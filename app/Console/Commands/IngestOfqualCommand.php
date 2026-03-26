<?php

namespace App\Console\Commands;

use App\Services\Ingestion\OfqualIngestionService;
use Illuminate\Console\Command;

class IngestOfqualCommand extends Command
{
    protected $signature = 'ingest:ofqual
                            {--run-date= : Run date in YYYY-MM-DD format (defaults to UTC today)}
                            {--base-dir= : Base directory for outputs (defaults to repository root)}';

    protected $description = 'Ingest Ofqual Organisations/Qualifications CSVs and write canonical + sqlite outputs';

    public function handle(OfqualIngestionService $service): int
    {
        $result = $service->run(
            $this->option('run-date') ?: null,
            $this->option('base-dir') ?: null,
        );

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
