<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Nexus\Dissemination\Application\Dto\FullTextResult;
use Nexus\Dissemination\Domain\BibliographyFormat;
use Nexus\Dissemination\Domain\ExportHistoryRecord;
use Nexus\Dissemination\Domain\ExportType;
use Nexus\Dissemination\Domain\Port\ExportHistoryPort;
use Nexus\Dissemination\Domain\Port\PdfFetchRepositoryPort;
use Nexus\Laravel\Job\SearchJob;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkProviderModel;
use Nexus\Shared\Port\JobLifecycleRecorderPort;
use Nexus\Shared\ValueObject\JobLifecycleRecord;
use Nexus\Shared\ValueObject\WorkId;
use Nexus\Shared\ValueObject\WorkIdNamespace;

uses(RefreshDatabase::class);

test('read commands expose export history jobs and full-text artifacts', function (): void {
    $projectId = 'cli-read-command-project';
    nexusCliReadSeedProject($projectId);

    $export = ExportHistoryRecord::create(
        type: ExportType::BIBLIOGRAPHY,
        format: BibliographyFormat::CSV->value,
        filename: 'exports/cli-read.csv',
        path: 'exports/cli-read.csv',
        mimeType: BibliographyFormat::CSV->mimeType(),
        sizeBytes: 42,
        projectId: $projectId,
        requestedBy: 'reviewer-1',
        metadata: ['citable' => true],
        createdAt: new DateTimeImmutable('2026-05-27T08:00:00+00:00'),
    );
    app(ExportHistoryPort::class)->record($export);

    app(JobLifecycleRecorderPort::class)->record(JobLifecycleRecord::completed(
        runId: 'cli-read-run-1',
        jobName: 'search',
        jobClass: SearchJob::class,
        context: ['project_id' => $projectId],
        summary: ['success_count' => 1],
        durationMs: 125,
        occurredAt: new DateTimeImmutable('2026-05-27T08:01:00+00:00'),
    ));

    app(PdfFetchRepositoryPort::class)->save(
        new WorkId(WorkIdNamespace::DOI, '10.5555/cli-read-artifact'),
        'https://example.org/cli-read.pdf',
        FullTextResult::success('pdfs/cli-read.pdf', 'unpaywall', 200, ['license' => 'cc-by']),
        75,
    );

    $this->artisan('nexus:exports', [
        '--project' => $projectId,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('exports/cli-read.csv');

    $exportJsonExitCode = Artisan::call('nexus:exports', [
        'id' => $export->id,
        '--json' => true,
    ]);

    expect($exportJsonExitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('"filename": "exports\/cli-read.csv"')
        ->toContain('"project_id": "cli-read-command-project"');

    $this->artisan('nexus:jobs', [
        '--run' => 'cli-read-run-1',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('cli-read-run-1');

    $this->artisan('nexus:jobs', [
        '--run' => 'cli-read-run-1',
        '--status' => true,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Status: completed');

    $this->artisan('nexus:full-text-artifacts', [
        '--project' => $projectId,
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('cli-read-work-1');

    $artifactJsonExitCode = Artisan::call('nexus:full-text-artifacts', [
        '--work' => 'doi:10.5555/cli-read-artifact',
        '--json' => true,
    ]);

    expect($artifactJsonExitCode)->toBe(0)
        ->and(Artisan::output())
        ->toContain('"source_alias": "unpaywall"')
        ->toContain('"file_path": "pdfs\/cli-read.pdf"');
});

test('read commands reject ambiguous selectors', function (): void {
    $this->artisan('nexus:jobs')
        ->assertExitCode(1)
        ->expectsOutputToContain('Provide exactly one of --run or --project.');

    $this->artisan('nexus:full-text-artifacts', [
        '--work' => 'cli-read-work-1',
        '--project' => 'cli-read-command-project',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Provide exactly one of --work or --project.');
});

function nexusCliReadSeedProject(string $projectId): void
{
    DB::table('projects')->insert([
        'id' => $projectId,
        'name' => $projectId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $query = SearchQueryModel::create([
        'id' => (string) Str::uuid(),
        'project_id' => $projectId,
        'query_text' => 'cli read commands',
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.'cli read commands'),
        'status' => 'completed',
    ]);

    ScholarlyWorkModel::create([
        'id' => 'cli-read-work-1',
        'title' => 'CLI read command fixture',
        'abstract' => 'A paper used by the CLI read command smoke.',
        'year' => 2026,
        'venue_name' => 'Nexus Smoke',
        'retrieved_at' => now(),
    ]);

    WorkExternalIdModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => 'cli-read-work-1',
        'namespace' => 'doi',
        'value' => '10.5555/cli-read-artifact',
        'is_primary' => true,
    ]);

    WorkProviderModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => 'cli-read-work-1',
        'provider_alias' => 'openalex',
        'provider_work_id' => 'WCLI-READ-1',
        'last_seen_at' => now(),
    ]);

    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $query->id,
        'work_id' => 'cli-read-work-1',
        'provider_alias' => 'openalex',
        'provider_work_id' => 'WCLI-READ-1',
        'rank' => 1,
    ]);
}
