<?php

use App\Support\Institutions\EnglandSchoolsIngestor;
use Illuminate\Support\Carbon;

it('parses CSV rows into normalized institution payload', function (): void {
    $csv = <<<'CSV'
URN,EstablishmentName,PhaseOfEducation (name),GOR (name),Postcode,EstablishmentStatus (name),TypeOfEstablishment (name),LA (name)
200001,Gamma School,All-through,North West,M1 1AA,Open,Free schools,Manchester
CSV;

    $rows = (new EnglandSchoolsIngestor())->parseCsv($csv, 'https://example.test/gias.csv', Carbon::parse('2026-03-26T00:00:00Z'));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['external_id'])->toBe('200001')
        ->and($rows[0]['institution_type'])->toBe('school')
        ->and($rows[0]['country'])->toBe('England')
        ->and($rows[0]['phase'])->toBe('All-through')
        ->and($rows[0]['metadata']['local_authority'])->toBe('Manchester');
});
