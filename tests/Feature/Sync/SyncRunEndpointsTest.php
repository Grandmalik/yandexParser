<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Domain\SyncTrigger;
use App\Modules\Sync\Infrastructure\Jobs\SyncOrganizationJob;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->user = User::factory()->create();
    $this->organizationId = organizationFor($this->user->id);
    $this->actingAs($this->user)->fromSpa();
});

it('queues a collection on request and describes the run', function (): void {
    $response = $this->postJson(route('api.v1.organizations.sync-runs.store', $this->organizationId))
        ->assertAccepted()
        ->assertJsonPath('data.organization_id', $this->organizationId)
        ->assertJsonPath('data.status', ['value' => 'queued', 'label' => __('sync.enums.status.queued')])
        ->assertJsonPath('data.trigger', 'manual')
        ->assertJsonPath('data.progress', ['current' => 0, 'total' => null, 'percent' => null])
        ->assertJsonPath('data.attempts', 0)
        ->assertJsonPath('data.error', null)
        ->assertJsonPath('data.stats', null);

    $this->assertDatabaseHas('sync_runs', ['id' => $response->json('data.id'), 'status' => 'queued', 'trigger' => 'manual']);
    Queue::assertPushedOn('scraping', SyncOrganizationJob::class);
});

it('refuses a second collection while one is already running', function (): void {
    $first = $this->postJson(route('api.v1.organizations.sync-runs.store', $this->organizationId))->json('data.id');

    $this->postJson(route('api.v1.organizations.sync-runs.store', $this->organizationId))
        ->assertConflict()
        ->assertJsonPath('error.code', 'sync.already_running')
        ->assertJsonPath('error.details.sync_run_id', $first);

    $this->assertDatabaseCount('sync_runs', 1);
});

it('returns the latest run of an organization', function (): void {
    $run = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

    $this->getJson(route('api.v1.organizations.sync-runs.latest', $this->organizationId))
        ->assertOk()
        ->assertJsonPath('data.id', $run->id)
        ->assertJsonPath('data.trigger', 'connected');
});

it('reports no run yet as not found', function (): void {
    $this->getJson(route('api.v1.organizations.sync-runs.latest', $this->organizationId))->assertNotFound();
});

it('shows a single run by id', function (): void {
    $run = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Manual);

    $this->getJson(route('api.v1.sync-runs.show', $run->id))
        ->assertOk()
        ->assertJsonPath('data.id', $run->id);
});

it('hides organizations and runs of other users', function (Closure $request): void {
    $stranger = User::factory()->create();
    $otherOrganization = organizationFor($stranger->id, '52335293875');
    $otherRun = app(RequestSync::class)->handle($otherOrganization, SyncTrigger::Manual);

    $request($this, $otherOrganization, $otherRun->id)->assertNotFound();
})->with([
    'request a collection' => [fn ($test, string $organization) => $test->postJson(route('api.v1.organizations.sync-runs.store', $organization))],
    'latest run' => [fn ($test, string $organization) => $test->getJson(route('api.v1.organizations.sync-runs.latest', $organization))],
    'run by id' => [fn ($test, string $organization, string $run) => $test->getJson(route('api.v1.sync-runs.show', $run))],
]);

it('reports an unknown run as not found', function (): void {
    $this->getJson(route('api.v1.sync-runs.show', Str::uuid7()->toString()))->assertNotFound();
});

it('throttles repeated manual requests', function (): void {
    $this->freezeTime();
    config(['sync.manual.max_attempts' => 1]);

    $this->postJson(route('api.v1.organizations.sync-runs.store', $this->organizationId))->assertAccepted();

    $this->postJson(route('api.v1.organizations.sync-runs.store', $this->organizationId))
        ->assertTooManyRequests()
        ->assertJsonPath('error.details.retry_after', config('sync.manual.decay_seconds'));
});

it('requires authentication', function (): void {
    auth()->logout();

    $this->getJson(route('api.v1.organizations.sync-runs.latest', $this->organizationId))->assertUnauthorized();
});
