<?php

use Illuminate\Support\Facades\Http;

it('runs all england ingestion pipeline successfully and prints summary', function (): void {
    $schoolsCsv = <<<'CSV'
URN,EstablishmentName,PhaseOfEducation (name),GOR (name),Postcode,EstablishmentStatus (name),TypeOfEstablishment (name),LA (name)
100001,Alpha School,Primary,London,E1 1AA,Open,Community school,Tower Hamlets
CSV;

    $organisationsCsv = <<<'CSV'
Recognition Number,Name
RN123,Example Awarding Org
CSV;

    $qualificationsCsv = <<<'CSV'
Qualification Number,Qualification Title
QN123,Example Qualification
CSV;

    Http::fake([
        'https://example.test/gias.csv' => Http::response($schoolsCsv, 200),
        'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Organisations.csv' => Http::response($organisationsCsv, 200),
        'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Qualifications.csv' => Http::response($qualificationsCsv, 200),
    ]);

    $this->artisan('ingest:all-england', [
        '--run-date' => '2026-03-26',
        '--csv-url' => 'https://example.test/gias.csv',
    ])
        ->expectsOutputToContain('Step 1/2: Institutions (England schools + placeholders)')
        ->expectsOutputToContain('Step 2/2: Ofqual organisations + qualifications')
        ->expectsOutputToContain('Ingestion summary:')
        ->expectsOutputToContain('✅ ingest:institutions-england')
        ->expectsOutputToContain('✅ ingest:ofqual')
        ->expectsOutputToContain('Latest report files:')
        ->assertSuccessful();
});

it('halts pipeline when institutions step fails', function (): void {
    Http::fake([
        'https://example.test/gias.csv' => Http::response('boom', 500),
        'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Organisations.csv' => Http::response('should-not-be-called', 200),
        'https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Qualifications.csv' => Http::response('should-not-be-called', 200),
    ]);

    $this->artisan('ingest:all-england', [
        '--run-date' => '2026-03-26',
        '--csv-url' => 'https://example.test/gias.csv',
    ])
        ->expectsOutputToContain('Step 1/2: Institutions (England schools + placeholders)')
        ->expectsOutputToContain('Pipeline halted after failed step: ingest:institutions-england')
        ->expectsOutputToContain('❌ ingest:institutions-england')
        ->assertFailed();

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'downloads.find-a-qualification.services.ofqual.gov.uk');
    });
});
