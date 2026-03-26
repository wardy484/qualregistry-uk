<?php

use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public institutions browse page', function () {
    Institution::create(institutionAttributes([
        'name' => 'Oakfield Academy',
        'institution_type' => 'Academy',
    ]));

    $this->get(route('institutions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institutions/Index')
            ->has('institutions.data', 1)
            ->where('institutions.data.0.name', 'Oakfield Academy')
            ->where('filters.search', '')
        );
});

it('filters institutions by search and institution_type', function () {
    Institution::create(institutionAttributes([
        'name' => 'North Bridge School',
        'institution_type' => 'School',
    ]));

    Institution::create(institutionAttributes([
        'external_id' => '2',
        'name' => 'North Bridge College',
        'institution_type' => 'College',
    ]));

    Institution::create(institutionAttributes([
        'external_id' => '3',
        'name' => 'South Hill School',
        'institution_type' => 'School',
    ]));

    $this->get(route('institutions.index', [
        'search' => 'North',
        'institution_type' => 'School',
    ]))->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Institutions/Index')
            ->has('institutions.data', 1)
            ->where('institutions.data.0.name', 'North Bridge School')
            ->where('filters.search', 'North')
            ->where('filters.institution_type', 'School')
        );
});

function institutionAttributes(array $overrides = []): array
{
    return array_merge([
        'external_id' => '1',
        'name' => 'Default Institution',
        'institution_type' => 'School',
        'phase' => 'Secondary',
        'country' => 'England',
        'region' => 'London',
        'postcode' => 'N1 1AA',
        'source_system' => 'test-suite',
        'source_url' => 'https://example.com/institution/1',
        'fetched_at' => now(),
        'imported_at' => now(),
        'metadata' => ['seeded' => true],
    ], $overrides);
}
