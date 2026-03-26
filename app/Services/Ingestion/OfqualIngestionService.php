<?php

namespace App\Services\Ingestion;

use App\Support\Ingestion\OfqualNormalizer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use SplFileObject;

class OfqualIngestionService
{
    private const SOURCES = [
        'organisations' => 'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Organisations.csv',
        'qualifications' => 'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Qualifications.csv',
    ];

    public function run(?string $runDate = null, ?string $baseDir = null): array
    {
        $baseDir ??= base_path();
        $runDate ??= gmdate('Y-m-d');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
            throw new InvalidArgumentException('run-date must use YYYY-MM-DD format.');
        }

        $runTimestamp = $this->utcNowIso();
        $fetchedAt = $this->utcNowIso();
        $importedAt = $this->utcNowIso();

        $rawDir = $baseDir.'/data/raw/'.OfqualNormalizer::SOURCE_SYSTEM."/{$runDate}";
        $canonicalDir = $baseDir."/data/canonical/{$runDate}";
        $reportDir = $baseDir.'/reports/ingestion/'.OfqualNormalizer::SOURCE_SYSTEM."/{$runDate}";
        $dbPath = $baseDir.'/storage/qualregistry.sqlite';

        $this->ensureDir($rawDir);
        $this->ensureDir($canonicalDir);
        $this->ensureDir($reportDir);
        $this->ensureDir(dirname($dbPath));

        $orgRawPath = $rawDir.'/Organisations.csv';
        $qualRawPath = $rawDir.'/Qualifications.csv';

        $checksums = [
            'Organisations.csv' => $this->download(self::SOURCES['organisations'], $orgRawPath),
            'Qualifications.csv' => $this->download(self::SOURCES['qualifications'], $qualRawPath),
        ];

        file_put_contents($rawDir.'/sha256sums.txt', collect($checksums)
            ->map(fn (string $digest, string $name) => "{$digest}  {$name}")
            ->implode(PHP_EOL).PHP_EOL);

        $orgRows = OfqualNormalizer::normalizeRows(
            $this->loadCsv($orgRawPath),
            OfqualNormalizer::ORG_FIELD_MAP,
            self::SOURCES['organisations'],
            $fetchedAt,
            $importedAt,
        );

        $qualRows = OfqualNormalizer::normalizeRows(
            $this->loadCsv($qualRawPath),
            OfqualNormalizer::QUAL_FIELD_MAP,
            self::SOURCES['qualifications'],
            $fetchedAt,
            $importedAt,
        );

        $awardingBodiesCsv = $canonicalDir.'/awarding_bodies.csv';
        $qualificationsCsv = $canonicalDir.'/qualifications.csv';

        $this->writeCsv($awardingBodiesCsv, $orgRows);
        $this->writeCsv($qualificationsCsv, $qualRows);

        $awardingCount = $this->loadIntoSqlite($dbPath, 'awarding_bodies', $awardingBodiesCsv);
        $qualificationsCount = $this->loadIntoSqlite($dbPath, 'qualifications', $qualificationsCsv);

        $report = [
            'run_date' => $runDate,
            'run_timestamp' => $runTimestamp,
            'source_system' => OfqualNormalizer::SOURCE_SYSTEM,
            'raw_dir' => str_replace($baseDir.'/', '', $rawDir),
            'canonical_dir' => str_replace($baseDir.'/', '', $canonicalDir),
            'database' => str_replace($baseDir.'/', '', $dbPath),
            'counts' => [
                'awarding_bodies' => $awardingCount,
                'qualifications' => $qualificationsCount,
            ],
            'checksums' => $checksums,
            'samples' => [
                'awarding_bodies' => array_slice($orgRows, 0, 3),
                'qualifications' => array_slice($qualRows, 0, 3),
            ],
        ];

        file_put_contents($reportDir.'/run-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($reportDir.'/run-report.md', $this->buildMarkdownReport($report));

        return [
            'status' => 'ok',
            'run_date' => $runDate,
            'counts' => $report['counts'],
            'report_json' => str_replace($baseDir.'/', '', $reportDir.'/run-report.json'),
        ];
    }

    private function utcNowIso(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    private function download(string $url, string $targetPath): string
    {
        $response = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (qualregistry-ingestion-poc)'])
            ->timeout(120)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Failed downloading {$url}. Status: {$response->status()}");
        }

        $payload = $response->body();
        file_put_contents($targetPath, $payload);

        return hash('sha256', $payload);
    }

    private function loadCsv(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

        $header = null;
        $rows = [];

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value)), $row);

                continue;
            }

            $assoc = [];

            foreach ($header as $index => $key) {
                $assoc[$key] = $row[$index] ?? null;
            }

            $rows[] = $assoc;
        }

        return $rows;
    }

    private function writeCsv(string $path, array $rows): void
    {
        if ($rows === []) {
            throw new RuntimeException("No rows to write for {$path}");
        }

        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open {$path} for writing.");
        }

        $header = array_keys($rows[0]);
        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn (string $key) => $row[$key] ?? '', $header));
        }

        fclose($handle);
    }

    private function loadIntoSqlite(string $dbPath, string $table, string $csvPath): int
    {
        $rows = $this->loadCsv($csvPath);

        if ($rows === []) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $pdo = new \PDO('sqlite:'.$dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $columnsSql = implode(', ', array_map(fn (string $column) => '"'.$column.'" TEXT', $columns));
        $pdo->exec("CREATE TABLE IF NOT EXISTS \"{$table}\" ({$columnsSql})");
        $pdo->exec("DELETE FROM \"{$table}\"");

        $columnListSql = implode(', ', array_map(fn (string $column) => '"'.$column.'"', $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $statement = $pdo->prepare("INSERT INTO \"{$table}\" ({$columnListSql}) VALUES ({$placeholders})");

        foreach ($rows as $row) {
            $statement->execute(array_map(fn (string $column) => $row[$column] ?? '', $columns));
        }

        return count($rows);
    }

    private function buildMarkdownReport(array $report): string
    {
        $markdown = [
            '# Ofqual ingestion run report',
            '',
            '- **Run date:** '.$report['run_date'],
            '- **Run timestamp (UTC):** '.$report['run_timestamp'],
            '- **Raw data dir:** `'.$report['raw_dir'].'`',
            '- **Canonical data dir:** `'.$report['canonical_dir'].'`',
            '- **SQLite DB:** `'.$report['database'].'`',
            '',
            '## Record counts',
            '- awarding_bodies: **'.$report['counts']['awarding_bodies'].'**',
            '- qualifications: **'.$report['counts']['qualifications'].'**',
            '',
            '## SHA256 checksums',
        ];

        foreach ($report['checksums'] as $name => $digest) {
            $markdown[] = '- `'.$name.'`: `'.$digest.'`';
        }

        $markdown[] = '';
        $markdown[] = '## Sample rows';
        $markdown[] = '';
        $markdown[] = '### awarding_bodies (first 3)';
        $markdown[] = '```json';
        $markdown[] = json_encode($report['samples']['awarding_bodies'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $markdown[] = '```';
        $markdown[] = '';
        $markdown[] = '### qualifications (first 3)';
        $markdown[] = '```json';
        $markdown[] = json_encode($report['samples']['qualifications'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $markdown[] = '```';
        $markdown[] = '';

        return implode(PHP_EOL, $markdown);
    }
}
