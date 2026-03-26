<?php

namespace App\Support\Ingestion;

class OfqualNormalizer
{
    public const SOURCE_SYSTEM = 'ofqual';

    public const ORG_FIELD_MAP = [
        'Recognition Number' => 'awarding_body_id',
        'Name' => 'name',
        'Legal Name' => 'legal_name',
        'Acronym' => 'acronym',
        'Email' => 'email',
        'Website' => 'website',
        'Head Office Address Line 1' => 'head_office_address_line_1',
        'Head Office Address Line 2' => 'head_office_address_line_2',
        'Head Office Address Town/City' => 'head_office_town_city',
        'Head Office Address County' => 'head_office_county',
        'Head Office Address Postcode' => 'head_office_postcode',
        'Head Office Address Country' => 'head_office_country',
        'Head Office Address Telephone Number' => 'head_office_phone',
        'Ofqual Status' => 'ofqual_status',
        'Ofqual Recognised From' => 'ofqual_recognised_from',
        'Ofqual Recognised To' => 'ofqual_recognised_to',
        'CCEA Regulation Status' => 'ccea_regulation_status',
        'CCEA Regulation Recognised From' => 'ccea_regulation_recognised_from',
        'CCEA Regulation Recognised To' => 'ccea_regulation_recognised_to',
    ];

    public const QUAL_FIELD_MAP = [
        'Qualification Number' => 'qualification_number',
        'Qualification Title' => 'qualification_title',
        'Owner Organisation Recognition Number' => 'owner_org_recognition_number',
        'Owner Organisation Name' => 'owner_org_name',
        'Owner Organisation Acronym' => 'owner_org_acronym',
        'Qualification Level' => 'qualification_level',
        'Qualification Sub Level' => 'qualification_sub_level',
        'EQF Level' => 'eqf_level',
        'Qualification Type' => 'qualification_type',
        'Total Credits' => 'total_credits',
        'Qualification SSA' => 'qualification_ssa',
        'Qualification Status' => 'qualification_status',
        'Regulation Start Date' => 'regulation_start_date',
        'Operational Start Date' => 'operational_start_date',
        'Operational End Date' => 'operational_end_date',
        'Certification End Date' => 'certification_end_date',
        'Minimum Guided Learning Hours' => 'minimum_guided_learning_hours',
        'Maximum Guided Learning Hours' => 'maximum_guided_learning_hours',
        'Total Qualification Time' => 'total_qualification_time',
        'Guided Learning Hours' => 'guided_learning_hours',
        'Offered In England' => 'offered_in_england',
        'Offered In Northern Ireland' => 'offered_in_northern_ireland',
        'Overall Grading Type' => 'overall_grading_type',
        'Assessment Methods' => 'assessment_methods',
        'NI Discount Code' => 'ni_discount_code',
        'GCE Size Equivalence' => 'gce_size_equivalence',
        'GCSE Size Equivalence' => 'gcse_size_equivalence',
        'Entitlement Framework Designation' => 'entitlement_framework_designation',
        'Grading Scale' => 'grading_scale',
        'Specialisms' => 'specialisms',
        'Pathways' => 'pathways',
        'Approved For DEL Funded Programme' => 'approved_for_del_funded_programme',
        'Link To Specification' => 'link_to_specification',
        'Currently and / or will consider offering internationally' => 'offered_internationally',
        'Apprenticeship Standard Reference Number' => 'apprenticeship_standard_reference_number',
        'Apprenticeship Standard Title' => 'apprenticeship_standard_title',
    ];

    public static function normalizeRow(array $row, array $fieldMap, string $sourceUrl, string $fetchedAt, string $importedAt): array
    {
        $normalized = [];

        foreach ($fieldMap as $sourceKey => $destinationKey) {
            $normalized[$destinationKey] = trim((string) ($row[$sourceKey] ?? ''));
        }

        $normalized['source_system'] = self::SOURCE_SYSTEM;
        $normalized['source_url'] = $sourceUrl;
        $normalized['fetched_at'] = $fetchedAt;
        $normalized['imported_at'] = $importedAt;

        return $normalized;
    }

    public static function normalizeRows(array $rows, array $fieldMap, string $sourceUrl, string $fetchedAt, string $importedAt): array
    {
        return array_map(
            fn (array $row) => self::normalizeRow($row, $fieldMap, $sourceUrl, $fetchedAt, $importedAt),
            $rows,
        );
    }
}
