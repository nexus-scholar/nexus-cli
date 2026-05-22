# nexus:screen

Screen scholarly works with inclusion/exclusion criteria.

The command has two modes:

- Run-file mode reads a local run JSON file and writes `storage/screens/{run_id}.json`.
- Project mode uses the database-backed `nexus-scholar/core` screening engine and writes `screening_runs`, `screening_decisions`, and `screening_votes`.

Project mode is the preferred path for real review work because it preserves provider provenance, criteria hashes, model votes, confidence, rationale, and audit metadata.

## Prerequisites

Run migrations before database-backed screening:

```powershell
php artisan migrate
```

For LLM screening, configure OpenRouter in `.env`. Do not commit real keys.

```dotenv
NEXUS_LLM_SCREENING_ENABLED=true
NEXUS_LLM_PROVIDER=openrouter
NEXUS_LLM_OPENROUTER_API_KEY=
NEXUS_LLM_SCREENING_MODEL=openai/gpt-4.1-mini
NEXUS_LLM_SCREENING_TEMPERATURE=0
NEXUS_LLM_SCREENING_MAX_TOKENS=600
NEXUS_LLM_SCREENING_TIMEOUT=45
```

After changing `.env`, clear cached config:

```powershell
php artisan config:clear
```

## Project Mode

Single-model title/abstract screening:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --model=openai/gpt-4.1-mini `
  --max=10 `
  --name="TomatoMAP title abstract smoke"
```

Bounded screening for one known work:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --work-ids=c17bc19e-0fa0-453f-bb1e-5879b7723148 `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --model=openai/gpt-4.1-mini `
  --max=1 `
  --name="TomatoMAP bounded LLM smoke"
```

Council screening with three independent model attempts:

```powershell
php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --mode=council `
  --council-models="openai/gpt-4.1-mini,google/gemini-2.5-flash,mistralai/mistral-small-2603" `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --max=3 `
  --name="TomatoMAP council smoke"
```

Use council models that support OpenRouter structured outputs. The core screening client asks the provider for JSON schema-constrained output, so a model can be available on OpenRouter but still fail this command if it cannot route `response_format` requests. The current smoke-tested default set is OpenAI, Gemini, and Mistral.

Useful filters:

```powershell
php artisan nexus:screen --project=tomatomap_label_efficiency --query-ids=openalex_tomatomap_core --max=25 --include="tomato segmentation"
php artisan nexus:screen --project=tomatomap_label_efficiency --work-ids=id-1,id-2 --include="instance segmentation"
```

Audit options:

```powershell
php artisan nexus:screen --project=tomatomap_label_efficiency --include="tomato segmentation" --store-prompts
php artisan nexus:screen --project=tomatomap_label_efficiency --include="tomato segmentation" --store-prompts --store-raw-responses
```

Use raw-response storage only for focused debugging because it can persist full model output.

## Criteria Files

Project mode accepts JSON or YAML criteria:

```powershell
php artisan nexus:screen --project=tomatomap_label_efficiency --criteria=storage/screening-criteria.yml --max=25
```

Example YAML:

```yaml
include:
  - crop image segmentation
  - label-efficient or semi-supervised visual recognition
exclude:
  - medical imaging
  - remote sensing only
```

## Inspecting Results

Show the latest screening runs:

```powershell
php -r '$db=new PDO("sqlite:database/database.sqlite"); foreach($db->query("select id,name,status,counts,created_at from screening_runs order by created_at desc limit 5") as $r){echo json_encode(["id"=>$r["id"],"name"=>$r["name"],"status"=>$r["status"],"counts"=>$r["counts"],"created_at"=>$r["created_at"]], JSON_UNESCAPED_SLASHES), PHP_EOL;}'
```

Inspect decisions and model votes for one run:

```powershell
php -r '$run="RUN_ID_HERE"; $db=new PDO("sqlite:database/database.sqlite"); $sql="select d.work_id,d.decision,d.decision_source,d.confidence,d.reason,v.provider,v.model,v.decision as vote_decision,v.confidence as vote_confidence,v.latency_ms from screening_decisions d left join screening_votes v on v.screening_decision_id=d.id where d.screening_run_id=".$db->quote($run); foreach($db->query($sql) as $r){echo json_encode($r, JSON_UNESCAPED_SLASHES), PHP_EOL;}'
```

## Run-File Mode

Run-file mode is kept for local JSON workflows:

```powershell
php artisan nexus:screen
php artisan nexus:screen storage/runs/all_20260505_000000.json
php artisan nexus:screen storage/runs/all_20260505_000000.json --criteria=storage/criteria.json
```

Criteria format:

```json
{
  "include": {
    "keywords": ["tomato", "instance segmentation"],
    "year_from": 2020,
    "year_to": 2026
  },
  "exclude": {
    "keywords": ["review", "survey"]
  }
}
```

Run-file mode can optionally call a locally bound `nexus.llm` callable, but database-backed project mode is the maintained LLM path.

## After Screening

For human review and model comparison:

```powershell
php artisan nexus:screen-adjudicate --example
php artisan nexus:screen-adjudicate --project=tomatomap_label_efficiency --actor=reviewer-1 --file=storage/adjudication/tomatomap-human.yml
php artisan nexus:screen-compare --project=tomatomap_label_efficiency --baseline-run=rules-run-id --candidate-run=human-run-id --stage=title_abstract
```

See:

- [nexus:screen-adjudicate](../nexus-screen-adjudicate/README.md)
- [nexus:screen-compare](../nexus-screen-compare/README.md)
