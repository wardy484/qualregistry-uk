<?php

use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('ingests england schools via CSV upsert with deterministic counts', function (): void {
    Storage::fake('local');

    $csv = <<<'CSV'
URN,EstablishmentName,PhaseOfEducation (name),GOR (name),Postcode,EstablishmentStatus (name),TypeOfEstablishment (name),LA (name)
100001,Alpha School,Primary,London,E1 1AA,Open,Community school,Tower Hamlets
100002,Beta School,Secondary,South East,OX1 1BB,Open,Academy sponsor led,Oxfordshire
CSV;

    Http::fake([
        'https://example.test/gias.csv' => Http::response($csv, 200),
    ]);

    $this->artisan('ingest:institutions-england', ['--csv-url' => 'https://example.test/gias.csv'])
        ->expectsOutput('Schools: inserted=2 updated=0 skipped=0')
        ->assertSuccessful();

    expect(Institution::count())->toBe(2);

    $this->artisan('ingest:institutions-england', ['--csv-url' => 'https://example.test/gias.csv'])
        ->expectsOutput('Schools: inserted=0 updated=0 skipped=2')
        ->assertSuccessful();

    expect(Institution::query()->where('external_id', '100001')->first()->name)->toBe('Alpha School');

    expect(Institution::count())->toBe(2);
});
