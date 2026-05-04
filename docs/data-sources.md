# Data Sources and File Conventions

## Query File

Path: resources/queries/thesis-queries.yml

Structure:
searches:
  - id: string (snake_case, unique)
    label: string
    query: string
    providers: [openalex, arxiv, s2, crossref, pubmed, doaj]
    limit: int (default 50)
    year_from: int (optional)

## Run Files

Path: storage/runs/
Naming: {query_id}_{YYYYMMDD_HHiiss}.json  (per query)
        all_{YYYYMMDD_HHiiss}.json          (global master)
        latest.json                          (stable pointer)

latest.json format:
{ "file": "storage/runs/all_20260505_003000.json", "run_at": "ISO8601" }

## Baseline File

Path: storage/baseline.json
Written once manually or via nexus:status --record-baseline
Format:
{
  "model": "Detectron2 Mask R-CNN",
  "labeled_count": 700,
  "bbox_ap": 2.8,
  "segm_ap": 8.2,
  "split": "v1",
  "recorded_at": "2026-05-05"
}

## Wiki Slug Convention

paper slugs: lowercase, hyphens, year suffix
Example: "yolov8-tomato-segmentation-2024"

concept slugs: lowercase, hyphens, no year
Example: "semi-supervised-instance-segmentation"

## Wiki Page Existence Rule

nexus:ingest checks: if docs/wiki/papers/{slug}.md exists → skip.
Agent checks: if thesis_relevance field is empty → page needs analysis.
