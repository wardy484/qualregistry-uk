# QualRegistry UK — PRD v0

## Vision
A public, trusted UK qualifications repository and API (England first, UK-wide architecture) for researchers, developers, and institutions.

## Goals (MVP)
1. Publish a polished browse experience for qualifications intelligence.
2. Ingest and normalize official source data with provenance.
3. Support monthly refresh runs with idempotent processing.
4. Prepare a stable public read API surface.

## Non-goals (MVP)
- Tutorful-specific subject mapping table/workflows.
- Paid plans, billing, and advanced monetization mechanics.
- Real-time refresh guarantees.

## Primary Users
- Researchers analyzing qualifications and awarding body data.
- Developers integrating qualification metadata into products.
- Ops/education stakeholders needing source-linked references.

## Product Decisions (locked)
- Stack: Laravel + React + Inertia + shadcn/ui
- Hosting: Laravel Cloud
- API access: public initially
- Refresh cadence: monthly
- Conflict policy: gov.uk / England-first priority
- NI v1 coverage: CCEA-only with confidence flags
- Brand (working): QualRegistry UK

## MVP Functional Scope
- Search/filter by nation, level, subject, awarding body
- Qualification detail page with provenance + source links
- Canonical entities: institutions, awarding bodies, qualifications, options, mappings
- Import reports: added/removed/changed records per run

## Success Metrics
- Completeness coverage by source/nation
- Provenance completeness rate
- Monthly import success rate
- API response quality (latency + docs coverage)
