# Nexus Read API Commands

Last updated: 2026-05-27

These commands are host presentation adapters over `nexus-scholar/core` reader ports. They should not query tables directly.

## Export History

```powershell
php artisan nexus:exports --project=tomatomap_label_efficiency --limit=10
php artisan nexus:exports --project=tomatomap_label_efficiency --type=bibliography --json
php artisan nexus:exports export-history-id --json
```

Backed by `Nexus\Dissemination\Domain\Port\ExportHistoryReaderPort`.

## Job Lifecycle

```powershell
php artisan nexus:jobs --run=run-20260527-001
php artisan nexus:jobs --run=run-20260527-001 --status
php artisan nexus:jobs --project=tomatomap_label_efficiency --limit=25
php artisan nexus:jobs --project=tomatomap_label_efficiency --json
```

Backed by `Nexus\Shared\Port\JobLifecycleReaderPort`.

## Full-Text Artifacts

```powershell
php artisan nexus:full-text-artifacts --project=tomatomap_label_efficiency --limit=25
php artisan nexus:full-text-artifacts --work=cli-work-id
php artisan nexus:full-text-artifacts --work=doi:10.5555/example --json
```

Backed by `Nexus\Dissemination\Domain\Port\FullTextFetchReaderPort`.

`--project` uses the core project corpus authority: locked projects read from the latest immutable corpus snapshot, and draft projects read from query-work membership.

## Validation

The fixture-backed command coverage is in `tests/Feature/Commands/NexusReadCommandsTest.php`.

Focused local check:

```powershell
php artisan test --filter=NexusReadCommandsTest
```
