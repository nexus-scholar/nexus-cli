# nexus:screen

Screen a run file using inclusion/exclusion criteria and write decisions to `storage/screens/{run_id}.json`.

## Usage

```powershell
php artisan nexus:screen
php artisan nexus:screen storage\runs\all_20260505_000000.json
php artisan nexus:screen --criteria=storage\criteria.json
```

## Criteria format

Create `storage/criteria.json` with this structure:

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

