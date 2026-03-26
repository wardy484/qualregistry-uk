<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $fillable = [
        'external_id',
        'name',
        'institution_type',
        'phase',
        'country',
        'region',
        'postcode',
        'source_system',
        'source_url',
        'fetched_at',
        'imported_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'fetched_at' => 'datetime',
            'imported_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
