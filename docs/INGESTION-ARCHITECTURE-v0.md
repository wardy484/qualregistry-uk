# QualRegistry UK — Ingestion Architecture v0

## Pipeline stages
1. Fetch adapters (Ofqual/QiW/SQA/CCEA)
2. Raw snapshot store (timestamped, immutable)
3. Normalize -> canonical staging tables
4. Validate required fields + enums + references
5. Conflict resolution policy application
6. Upsert to canonical tables (idempotent)
7. Diff generation (added/changed/removed)
8. Publish run report + health signals

## Scheduler
- Monthly scheduled run (single orchestrator job)
- Manual rerun allowed for failed source adapters

## Idempotency
- import_batch_id + source_id unique constraints
- hash-based change detection on canonical payload
- repeat run with same source snapshot produces zero net mutation

## Failure handling
- Source-level retries with backoff
- Partial-source failure marks batch degraded (not silent success)
- Report includes source status summary + error excerpts

## Artifacts per run
- raw snapshots by source
- normalized staging exports
- canonical diff report
- validation + anomaly report
