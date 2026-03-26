# QualRegistry UK — Canonical Data Contract v0

## Shared fields (all entities)
- id (uuid)
- source_system (enum: ofqual|qiw|sqa|ccea|other)
- source_id (string)
- source_url (string|null)
- fetched_at (datetime)
- extracted_at (datetime)
- confidence (0..1)
- nation (enum: england|wales|scotland|ni|uk)
- import_batch_id (uuid)
- created_at / updated_at

## awarding_bodies
- id, canonical_name, short_name, regulator_reference, website_url, status

## qualifications
- id, awarding_body_id, title, qualification_type, level, eqf_level, status,
  start_date, end_date, certification_end_date, glh, tqt

## qualification_options
- id, qualification_id, option_code, option_title, option_type, status, metadata_json

## institutions
- id, urn_or_equivalent, name, institution_type, region, country, status

## institution_qualification_mappings
- id, institution_id, qualification_id, evidence_type, evidence_ref,
  mapping_status, confidence

## conflict tracking
- record_conflicts table:
  - entity_type, canonical_id, field_name, preferred_value, alternate_values_json,
    resolution_rule, resolved_by (system|manual), resolved_at

## precedence rule (v0)
1. Official gov/regulator sources (England-first where conflicting)
2. Nation official sources
3. Institution-level sources

## out of scope
- Tutorful-specific subject map table
