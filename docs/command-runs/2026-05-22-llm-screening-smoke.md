# 2026-05-22 LLM Screening Smoke

Purpose: verify that `nexus:screen` can call the real OpenRouter-backed screening path through `nexus-scholar/core` and persist the result in the local SQLite database.

## Environment

- Repository: `nexus-scholar/nexus-cli`
- Host app: Laravel Artisan CLI
- Database: `database/database.sqlite`
- Project: `tomatomap_label_efficiency`
- Work id: `c17bc19e-0fa0-453f-bb1e-5879b7723148`
- LLM provider: OpenRouter
- Requested model: `openai/gpt-4.1-mini`
- Key handling: API key presence was verified without printing the key. The key was present in Laravel `.env`.

## Command

```powershell
$env:NEXUS_LLM_SCREENING_ENABLED = 'true'
php artisan config:clear

php artisan nexus:screen `
  --project=tomatomap_label_efficiency `
  --work-ids=c17bc19e-0fa0-453f-bb1e-5879b7723148 `
  --include="crop image segmentation" `
  --include="label-efficient or semi-supervised visual recognition" `
  --exclude="medical imaging" `
  --exclude="remote sensing only" `
  --model=openai/gpt-4.1-mini `
  --max=1 `
  --name="TomatoMAP delegated LLM smoke 20260522_112556"
```

Exit code: `0`.

## CLI Output

```text
Screening complete.
Run: e1bd4964376618d65f31c79ce478b169 | Total: 1 | Include: 1 | Needs review: 0 | Exclude: 0 | Failed: 0
```

## Database Result

- Run id: `e1bd4964376618d65f31c79ce478b169`
- Run status: `completed`
- Counts: total `1`, included `1`, needs review `0`, excluded `0`, failed `0`
- Decision: `include`
- Decision source: `llm_single`
- Reason: paper matched crop image segmentation and label-efficient/semi-supervised visual recognition criteria
- Vote provider: `openrouter`
- Vote model: `openai/gpt-4.1-mini-2025-04-14`
- Vote confidence: `0.9`
- Vote latency: `2991 ms`

## Inspection Commands

Latest screening runs:

```powershell
php -r '$db=new PDO("sqlite:database/database.sqlite"); foreach($db->query("select id,name,status,counts,created_at from screening_runs order by created_at desc limit 5") as $r){echo json_encode(["id"=>$r["id"],"name"=>$r["name"],"status"=>$r["status"],"counts"=>$r["counts"],"created_at"=>$r["created_at"]], JSON_UNESCAPED_SLASHES), PHP_EOL;}'
```

Votes for this run:

```powershell
php -r '$run="e1bd4964376618d65f31c79ce478b169"; $db=new PDO("sqlite:database/database.sqlite"); $sql="select d.work_id,d.decision,d.decision_source,d.confidence,d.reason,v.provider,v.model,v.decision as vote_decision,v.confidence as vote_confidence,v.latency_ms from screening_decisions d left join screening_votes v on v.screening_decision_id=d.id where d.screening_run_id=".$db->quote($run); foreach($db->query($sql) as $r){echo json_encode($r, JSON_UNESCAPED_SLASHES), PHP_EOL;}'
```

## Notes

- This was intentionally bounded to one work so a real provider call could be verified without burning unnecessary tokens.
- Prompt and raw response storage were not enabled for this smoke run.
- The run proves the Laravel host can route to the reusable core screening engine, call OpenRouter, persist the decision, and preserve vote metadata.
