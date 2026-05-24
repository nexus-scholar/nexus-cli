# nexus:fetch-pdfs

Backward-compatible alias for `nexus:fetch-full-text`.

New documentation and scripts should prefer:

```powershell
php artisan nexus:fetch-full-text storage/screens/all_20260520_120000.json
```

The alias accepts the same arguments and options:

```text
{screen?}
--destination=
--max-attempts=2
--max-bytes=50000000
--cooldown=3600
--json
```

See `docs/commands/nexus-fetch-full-text/README.md` for the full behavior, output, and JSON summary documentation.
