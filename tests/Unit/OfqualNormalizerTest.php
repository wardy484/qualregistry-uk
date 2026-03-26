<?php

use App\Support\Ingestion\OfqualNormalizer;

it('normalizes an organisation row with trimmed values and metadata', function () {
    $row = [
        'Recognition Number' => ' RN123 ',
        'Name' => ' Example Awarding Body ',
        'Email' => ' info@example.com ',
    ];

    $normalized = OfqualNormalizer::normalizeRow(
        $row,
        OfqualNormalizer::ORG_FIELD_MAP,
        'https://example.com/organisations.csv',
        '2026-03-26T04:00:00Z',
        '2026-03-26T04:01:00Z',
    );

    expect($normalized['awarding_body_id'])->toBe('RN123')
        ->and($normalized['name'])->toBe('Example Awarding Body')
        ->and($normalized['email'])->toBe('info@example.com')
        ->and($normalized['source_system'])->toBe('ofqual')
        ->and($normalized['source_url'])->toBe('https://example.com/organisations.csv');
});

it('normalizes multiple qualification rows and backfills missing keys with empty strings', function () {
    $rows = [
        [
            'Qualification Number' => ' 601/0001/1 ',
            'Qualification Title' => ' Test Qualification ',
        ],
        [
            'Qualification Number' => null,
            'Qualification Title' => ' Second Qualification ',
        ],
    ];

    $normalizedRows = OfqualNormalizer::normalizeRows(
        $rows,
        OfqualNormalizer::QUAL_FIELD_MAP,
        'https://example.com/qualifications.csv',
        '2026-03-26T04:00:00Z',
        '2026-03-26T04:01:00Z',
    );

    expect($normalizedRows)->toHaveCount(2)
        ->and($normalizedRows[0]['qualification_number'])->toBe('601/0001/1')
        ->and($normalizedRows[1]['qualification_number'])->toBe('')
        ->and($normalizedRows[1]['qualification_type'])->toBe('');
});
