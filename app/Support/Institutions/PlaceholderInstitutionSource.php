<?php

namespace App\Support\Institutions;

class PlaceholderInstitutionSource
{
    public function __construct(
        private readonly string $label,
        private readonly ?string $configKey = null,
    ) {}

    public function run(): array
    {
        return [
            'status' => 'skipped',
            'source' => $this->label,
            'message' => sprintf(
                '%s ingestion not configured yet. TODO: wire authoritative FE/HE source (%s).',
                $this->label,
                $this->configKey ?? 'source URL'
            ),
        ];
    }
}
