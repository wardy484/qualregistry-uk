#!/usr/bin/env python3
"""Ingest Ofqual CSV extracts into raw/canonical/report/sqlite outputs."""

from __future__ import annotations

import argparse
import csv
import datetime as dt
import hashlib
import json
import sqlite3
from pathlib import Path
from typing import Dict, List
from urllib.request import Request, urlopen

SOURCE_SYSTEM = "ofqual"
SOURCES = {
    "organisations": "https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Organisations.csv",
    "qualifications": "https://downloads.find-a-qualification.services.ofqual.gov.uk/extracts/Qualifications.csv",
}

ORG_FIELD_MAP = {
    "Recognition Number": "awarding_body_id",
    "Name": "name",
    "Legal Name": "legal_name",
    "Acronym": "acronym",
    "Email": "email",
    "Website": "website",
    "Head Office Address Line 1": "head_office_address_line_1",
    "Head Office Address Line 2": "head_office_address_line_2",
    "Head Office Address Town/City": "head_office_town_city",
    "Head Office Address County": "head_office_county",
    "Head Office Address Postcode": "head_office_postcode",
    "Head Office Address Country": "head_office_country",
    "Head Office Address Telephone Number": "head_office_phone",
    "Ofqual Status": "ofqual_status",
    "Ofqual Recognised From": "ofqual_recognised_from",
    "Ofqual Recognised To": "ofqual_recognised_to",
    "CCEA Regulation Status": "ccea_regulation_status",
    "CCEA Regulation Recognised From": "ccea_regulation_recognised_from",
    "CCEA Regulation Recognised To": "ccea_regulation_recognised_to",
}

QUAL_FIELD_MAP = {
    "Qualification Number": "qualification_number",
    "Qualification Title": "qualification_title",
    "Owner Organisation Recognition Number": "owner_org_recognition_number",
    "Owner Organisation Name": "owner_org_name",
    "Owner Organisation Acronym": "owner_org_acronym",
    "Qualification Level": "qualification_level",
    "Qualification Sub Level": "qualification_sub_level",
    "EQF Level": "eqf_level",
    "Qualification Type": "qualification_type",
    "Total Credits": "total_credits",
    "Qualification SSA": "qualification_ssa",
    "Qualification Status": "qualification_status",
    "Regulation Start Date": "regulation_start_date",
    "Operational Start Date": "operational_start_date",
    "Operational End Date": "operational_end_date",
    "Certification End Date": "certification_end_date",
    "Minimum Guided Learning Hours": "minimum_guided_learning_hours",
    "Maximum Guided Learning Hours": "maximum_guided_learning_hours",
    "Total Qualification Time": "total_qualification_time",
    "Guided Learning Hours": "guided_learning_hours",
    "Offered In England": "offered_in_england",
    "Offered In Northern Ireland": "offered_in_northern_ireland",
    "Overall Grading Type": "overall_grading_type",
    "Assessment Methods": "assessment_methods",
    "NI Discount Code": "ni_discount_code",
    "GCE Size Equivalence": "gce_size_equivalence",
    "GCSE Size Equivalence": "gcse_size_equivalence",
    "Entitlement Framework Designation": "entitlement_framework_designation",
    "Grading Scale": "grading_scale",
    "Specialisms": "specialisms",
    "Pathways": "pathways",
    "Approved For DEL Funded Programme": "approved_for_del_funded_programme",
    "Link To Specification": "link_to_specification",
    "Currently and / or will consider offering internationally": "offered_internationally",
    "Apprenticeship Standard Reference Number": "apprenticeship_standard_reference_number",
    "Apprenticeship Standard Title": "apprenticeship_standard_title",
}


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run Ofqual ingestion POC")
    parser.add_argument("--run-date", help="Run date (YYYY-MM-DD), defaults to UTC today")
    parser.add_argument("--base-dir", default=".", help="Repository root path")
    return parser.parse_args()


def utc_now_iso() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def download(url: str, target: Path) -> str:
    req = Request(url, headers={"User-Agent": "Mozilla/5.0 (qualregistry-ingestion-poc)"})
    with urlopen(req, timeout=120) as resp:
        payload = resp.read()
    target.write_bytes(payload)
    return hashlib.sha256(payload).hexdigest()


def load_csv(path: Path) -> List[Dict[str, str]]:
    with path.open("r", encoding="utf-8-sig", newline="") as f:
        return list(csv.DictReader(f))


def transform(rows: List[Dict[str, str]], field_map: Dict[str, str], source_url: str, fetched_at: str, imported_at: str) -> List[Dict[str, str]]:
    out = []
    for row in rows:
        item = {dest: (row.get(src, "") or "").strip() for src, dest in field_map.items()}
        item["source_system"] = SOURCE_SYSTEM
        item["source_url"] = source_url
        item["fetched_at"] = fetched_at
        item["imported_at"] = imported_at
        out.append(item)
    return out


def write_csv(path: Path, rows: List[Dict[str, str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if not rows:
        raise ValueError(f"No rows to write for {path}")
    with path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        writer.writeheader()
        writer.writerows(rows)


def create_table(conn: sqlite3.Connection, table: str, columns: List[str]) -> None:
    cols_sql = ", ".join([f'"{c}" TEXT' for c in columns])
    conn.execute(f'CREATE TABLE IF NOT EXISTS "{table}" ({cols_sql})')


def load_into_sqlite(db_path: Path, table: str, csv_path: Path) -> int:
    with csv_path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f)
        rows = list(reader)
        if not rows:
            return 0
        cols = reader.fieldnames or []

    db_path.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(db_path)
    try:
        create_table(conn, table, cols)
        conn.execute(f'DELETE FROM "{table}"')
        placeholders = ", ".join(["?"] * len(cols))
        col_sql = ", ".join([f'"{c}"' for c in cols])
        conn.executemany(
            f'INSERT INTO "{table}" ({col_sql}) VALUES ({placeholders})',
            [[row.get(c, "") for c in cols] for row in rows],
        )
        conn.commit()
    finally:
        conn.close()
    return len(rows)


def main() -> None:
    args = parse_args()
    base_dir = Path(args.base_dir).resolve()
    run_date = args.run_date or dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%d")
    run_timestamp = utc_now_iso()

    raw_dir = base_dir / "data" / "raw" / SOURCE_SYSTEM / run_date
    canonical_dir = base_dir / "data" / "canonical" / run_date
    report_dir = base_dir / "reports" / "ingestion" / SOURCE_SYSTEM / run_date
    db_path = base_dir / "storage" / "qualregistry.sqlite"

    raw_dir.mkdir(parents=True, exist_ok=True)
    checksums = {}
    fetched_at = utc_now_iso()

    org_raw_path = raw_dir / "Organisations.csv"
    qual_raw_path = raw_dir / "Qualifications.csv"
    checksums[org_raw_path.name] = download(SOURCES["organisations"], org_raw_path)
    checksums[qual_raw_path.name] = download(SOURCES["qualifications"], qual_raw_path)

    checksum_lines = [f"{digest}  {name}" for name, digest in checksums.items()]
    (raw_dir / "sha256sums.txt").write_text("\n".join(checksum_lines) + "\n", encoding="utf-8")

    imported_at = utc_now_iso()
    org_rows = transform(load_csv(org_raw_path), ORG_FIELD_MAP, SOURCES["organisations"], fetched_at, imported_at)
    qual_rows = transform(load_csv(qual_raw_path), QUAL_FIELD_MAP, SOURCES["qualifications"], fetched_at, imported_at)

    awarding_bodies_csv = canonical_dir / "awarding_bodies.csv"
    qualifications_csv = canonical_dir / "qualifications.csv"
    write_csv(awarding_bodies_csv, org_rows)
    write_csv(qualifications_csv, qual_rows)

    awarding_count = load_into_sqlite(db_path, "awarding_bodies", awarding_bodies_csv)
    qualifications_count = load_into_sqlite(db_path, "qualifications", qualifications_csv)

    report = {
        "run_date": run_date,
        "run_timestamp": run_timestamp,
        "source_system": SOURCE_SYSTEM,
        "raw_dir": str(raw_dir.relative_to(base_dir)),
        "canonical_dir": str(canonical_dir.relative_to(base_dir)),
        "database": str(db_path.relative_to(base_dir)),
        "counts": {
            "awarding_bodies": awarding_count,
            "qualifications": qualifications_count,
        },
        "checksums": checksums,
        "samples": {
            "awarding_bodies": org_rows[:3],
            "qualifications": qual_rows[:3],
        },
    }

    report_dir.mkdir(parents=True, exist_ok=True)
    (report_dir / "run-report.json").write_text(json.dumps(report, indent=2), encoding="utf-8")

    md = [
        "# Ofqual ingestion run report",
        "",
        f"- **Run date:** {run_date}",
        f"- **Run timestamp (UTC):** {run_timestamp}",
        f"- **Raw data dir:** `{report['raw_dir']}`",
        f"- **Canonical data dir:** `{report['canonical_dir']}`",
        f"- **SQLite DB:** `{report['database']}`",
        "",
        "## Record counts",
        f"- awarding_bodies: **{awarding_count}**",
        f"- qualifications: **{qualifications_count}**",
        "",
        "## SHA256 checksums",
    ]
    for name, digest in checksums.items():
        md.append(f"- `{name}`: `{digest}`")
    md.extend([
        "",
        "## Sample rows",
        "",
        "### awarding_bodies (first 3)",
        "```json",
        json.dumps(org_rows[:3], indent=2),
        "```",
        "",
        "### qualifications (first 3)",
        "```json",
        json.dumps(qual_rows[:3], indent=2),
        "```",
        "",
    ])
    (report_dir / "run-report.md").write_text("\n".join(md), encoding="utf-8")

    print(json.dumps({
        "status": "ok",
        "run_date": run_date,
        "counts": report["counts"],
        "report_json": str((report_dir / "run-report.json").relative_to(base_dir)),
    }, indent=2))


if __name__ == "__main__":
    main()
