<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nexus\Laravel\Model\QueryWorkModel;
use Nexus\Laravel\Model\ScholarlyWorkModel;
use Nexus\Laravel\Model\SearchQueryModel;
use Nexus\Laravel\Model\WorkExternalIdModel;
use Nexus\Laravel\Model\WorkProviderModel;

uses(RefreshDatabase::class);

test('corpus lock creates a snapshot and bibliography export records final citable metadata', function (): void {
    Storage::fake('public');
    config()->set('nexus.dissemination.pdf_storage_disk', 'public');

    $projectId = 'cli-lock-export-project';
    createCliSnapshotProject($projectId);

    $this->artisan('nexus:corpus-lock', [
        '--project' => $projectId,
        '--actor' => 'reviewer-1',
        '--reason' => 'final local smoke',
        '--metadata' => ['source=feature-test'],
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Corpus locked.')
        ->expectsOutputToContain('Snapshot works: 1');

    $snapshotId = DB::table('corpus_snapshots')->where('project_id', $projectId)->value('id');

    expect($snapshotId)->not->toBeNull();
    $this->assertDatabaseHas('corpus_snapshot_works', [
        'snapshot_id' => $snapshotId,
        'work_id' => 'cli-snapshot-work-1',
    ]);

    $this->artisan('nexus:export-bibliography', [
        '--project' => $projectId,
        '--format' => 'csv',
        '--output' => 'exports/cli-lock-export.csv',
        '--requested-by' => 'reviewer-1',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Bibliography exported.')
        ->expectsOutputToContain('Works: 1')
        ->expectsOutputToContain('Snapshot: '.$snapshotId)
        ->expectsOutputToContain('Citable: yes')
        ->expectsOutputToContain('Final: yes');

    Storage::disk('public')->assertExists('exports/cli-lock-export.csv');

    $metadata = json_decode(
        DB::table('export_histories')->where('filename', 'exports/cli-lock-export.csv')->value('metadata'),
        true,
    );

    expect($metadata)->toMatchArray([
        'project_locked' => true,
        'corpus_snapshot_id' => $snapshotId,
        'snapshot_work_count' => 1,
        'citable' => true,
        'final' => true,
    ]);
});

test('draft bibliography export remains non-final and non-citable', function (): void {
    Storage::fake('public');
    config()->set('nexus.dissemination.pdf_storage_disk', 'public');

    $projectId = 'cli-draft-export-project';
    createCliSnapshotProject($projectId);

    $this->artisan('nexus:export-bibliography', [
        '--project' => $projectId,
        '--format' => 'csv',
        '--output' => 'exports/cli-draft-export.csv',
    ])
        ->assertExitCode(0)
        ->expectsOutputToContain('Bibliography exported.')
        ->expectsOutputToContain('Works: 1')
        ->expectsOutputToContain('Snapshot: none')
        ->expectsOutputToContain('Citable: no')
        ->expectsOutputToContain('Final: no');

    $metadata = json_decode(
        DB::table('export_histories')->where('filename', 'exports/cli-draft-export.csv')->value('metadata'),
        true,
    );

    expect($metadata)->toMatchArray([
        'project_locked' => false,
        'corpus_snapshot_id' => null,
        'citable' => false,
        'final' => false,
    ]);
});

function createCliSnapshotProject(string $projectId): void
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
        'query_text' => 'tomato segmentation',
        'max_results' => 50,
        'cache_key' => hash('sha256', $projectId.'tomato segmentation'),
        'status' => 'completed',
    ]);

    ScholarlyWorkModel::create([
        'id' => 'cli-snapshot-work-1',
        'title' => 'Tomato segmentation with sparse labels',
        'abstract' => 'A tomato segmentation paper.',
        'year' => 2025,
        'venue_name' => 'Plant Methods',
        'retrieved_at' => now(),
    ]);

    WorkExternalIdModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => 'cli-snapshot-work-1',
        'namespace' => 'doi',
        'value' => '10.5555/cli-snapshot',
        'is_primary' => true,
    ]);

    WorkProviderModel::create([
        'id' => (string) Str::uuid(),
        'work_id' => 'cli-snapshot-work-1',
        'provider_alias' => 'openalex',
        'provider_work_id' => 'WCLI1',
        'last_seen_at' => now(),
    ]);

    QueryWorkModel::create([
        'id' => (string) Str::uuid(),
        'search_query_id' => $query->id,
        'work_id' => 'cli-snapshot-work-1',
        'provider_alias' => 'openalex',
        'provider_work_id' => 'WCLI1',
        'rank' => 1,
    ]);
}
