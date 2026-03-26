<?php

namespace App\Support\Institutions;

use App\Models\Institution;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EnglandSchoolsIngestor
{
    private const SOURCE_SYSTEM = 'gias';

    private const GIAS_DOWNLOADS_URL = 'https://ea-edubase-api-prod.azurewebsites.net/edubase/downloads/public';

    public function ingest(?string $forcedCsvUrl = null): array
    {
        $fetchedAt = Carbon::now()->utc();
        $csvUrl = $this->resolveCsvUrl($forcedCsvUrl);
        $csv = $this->downloadCsv($csvUrl);

        $rows = $this->parseCsv($csv, $csvUrl, $fetchedAt);

        return $this->upsertRows($rows);
    }

    public function parseCsv(string $csv, string $sourceUrl, Carbon $fetchedAt): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        if (! $lines || count($lines) < 2) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $index = $this->headerIndexMap($headers);

        $parsed = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $cols = str_getcsv($line);

            $urn = $this->getColumn($cols, $index, ['URN', 'URN (Institution)']);
            $laestab = $this->getColumn($cols, $index, ['LA (code)', 'EstablishmentNumber', 'LAESTAB']);
            $externalId = $urn ?: $laestab;

            if (! $externalId) {
                continue;
            }

            $name = $this->getColumn($cols, $index, ['EstablishmentName', 'Establishment Name', 'InstitutionName']) ?: 'Unknown Institution';
            $phase = $this->getColumn($cols, $index, ['PhaseOfEducation (name)', 'PhaseOfEducation', 'Phase']);
            $region = $this->getColumn($cols, $index, ['GOR (name)', 'Region', 'DistrictAdministrative (name)']);
            $postcode = $this->getColumn($cols, $index, ['Postcode', 'PostCode']);

            $parsed[] = [
                'external_id' => trim($externalId),
                'name' => trim($name),
                'institution_type' => 'school',
                'phase' => $phase ? trim($phase) : null,
                'country' => 'England',
                'region' => $region ? trim($region) : null,
                'postcode' => $postcode ? trim($postcode) : null,
                'source_system' => self::SOURCE_SYSTEM,
                'source_url' => $sourceUrl,
                'fetched_at' => $fetchedAt,
                'imported_at' => Carbon::now()->utc(),
                'metadata' => [
                    'status' => $this->getColumn($cols, $index, ['EstablishmentStatus (name)', 'EstablishmentStatus']) ?: null,
                    'type' => $this->getColumn($cols, $index, ['TypeOfEstablishment (name)', 'TypeOfEstablishment']) ?: null,
                    'local_authority' => $this->getColumn($cols, $index, ['LA (name)', 'LocalAuthority (name)']) ?: null,
                    'raw_identifiers' => [
                        'urn' => $urn,
                        'laestab' => $laestab,
                    ],
                ],
            ];
        }

        // deterministic by key
        usort($parsed, fn (array $a, array $b): int => strcmp($a['external_id'], $b['external_id']));

        return $parsed;
    }

    private function upsertRows(array $rows): array
    {
        if ($rows === []) {
            return ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'processed' => 0];
        }

        $existing = Institution::query()
            ->where('source_system', self::SOURCE_SYSTEM)
            ->where('institution_type', 'school')
            ->get()
            ->mapWithKeys(fn (Institution $institution): array => [
                $this->identityKey($institution->toArray()) => $institution,
            ]);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $upsertPayload = [];

        foreach ($rows as $row) {
            $existingRecord = $existing->get($this->identityKey($row));

            if (! $existingRecord) {
                $inserted++;
                $upsertPayload[] = $row;

                continue;
            }

            if ($this->isDifferent($existingRecord, $row)) {
                $updated++;
                $upsertPayload[] = $row;
            } else {
                $skipped++;
            }
        }

        if ($upsertPayload !== []) {
            $timestamp = Carbon::now()->utc();
            $payload = array_map(function (array $row) use ($timestamp): array {
                $row['metadata'] = json_encode($row['metadata'], JSON_UNESCAPED_UNICODE);
                $row['created_at'] = $timestamp;
                $row['updated_at'] = $timestamp;

                return $row;
            }, $upsertPayload);

            Institution::query()->upsert(
                $payload,
                ['external_id', 'institution_type', 'source_system'],
                ['name', 'phase', 'country', 'region', 'postcode', 'source_url', 'fetched_at', 'imported_at', 'metadata', 'updated_at']
            );
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'processed' => count($rows),
            'download_url' => Arr::first($rows)['source_url'] ?? null,
        ];
    }

    private function isDifferent(Institution $existing, array $incoming): bool
    {
        $existingSubset = [
            'name' => $existing->name,
            'phase' => $existing->phase,
            'country' => $existing->country,
            'region' => $existing->region,
            'postcode' => $existing->postcode,
            'source_url' => $existing->source_url,
            'metadata' => $existing->metadata,
        ];

        $incomingSubset = [
            'name' => $incoming['name'],
            'phase' => $incoming['phase'],
            'country' => $incoming['country'],
            'region' => $incoming['region'],
            'postcode' => $incoming['postcode'],
            'source_url' => $incoming['source_url'],
            'metadata' => $incoming['metadata'],
        ];

        return json_encode($existingSubset) !== json_encode($incomingSubset);
    }

    private function identityKey(array $row): string
    {
        return sprintf('%s|%s|%s', $row['source_system'], $row['institution_type'], $row['external_id']);
    }

    private function resolveCsvUrl(?string $forcedCsvUrl = null): string
    {
        if ($forcedCsvUrl) {
            return $forcedCsvUrl;
        }

        $configured = config('services.gias.schools_csv_url') ?: env('GIAS_SCHOOLS_CSV_URL');

        if ($configured) {
            return $configured;
        }

        $html = Http::timeout(30)->get(self::GIAS_DOWNLOADS_URL)->throw()->body();

        if (preg_match('/href="([^"]*edubasealldata\d{8}\.csv)"/i', $html, $matches)) {
            $path = html_entity_decode($matches[1]);

            if (str_starts_with($path, 'http')) {
                return $path;
            }

            return rtrim(self::GIAS_DOWNLOADS_URL, '/').'/'.ltrim($path, '/');
        }

        throw new RuntimeException('Unable to auto-discover GIAS CSV URL. Set GIAS_SCHOOLS_CSV_URL.');
    }

    private function downloadCsv(string $csvUrl): string
    {
        $response = Http::timeout(120)->get($csvUrl)->throw();

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('Downloaded CSV is empty.');
        }

        Storage::disk('local')->put(
            sprintf('ingestion/raw/institutions-england-%s.csv', now()->utc()->format('Ymd_His')),
            $body
        );

        return $body;
    }

    private function headerIndexMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $i => $header) {
            $map[trim((string) $header)] = $i;
        }

        return $map;
    }

    private function getColumn(array $cols, array $headerMap, array $candidateHeaders): ?string
    {
        foreach ($candidateHeaders as $header) {
            if (! array_key_exists($header, $headerMap)) {
                continue;
            }

            $value = $cols[$headerMap[$header]] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
