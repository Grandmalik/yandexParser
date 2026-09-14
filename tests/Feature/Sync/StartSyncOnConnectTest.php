<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Sync\Infrastructure\Jobs\SyncOrganizationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();

    $this->actingAs(User::factory()->create())->fromSpa();
});

it('starts collecting data as soon as an organization is connected', function (): void {
    $organizationId = $this->postJson(route('api.v1.organizations.store'), ['url' => 'https://yandex.ru/maps/org/kofeynya/1234567890/'])
        ->assertAccepted()
        ->json('data.id');

    $this->assertDatabaseHas('sync_runs', ['organization_id' => $organizationId, 'status' => 'queued', 'trigger' => 'connected']);
    Queue::assertPushedOn('scraping', SyncOrganizationJob::class);
});

it('joins the running collection instead of queueing a second one', function (): void {
    $url = 'https://yandex.ru/maps/org/kofeynya/1234567890/';
    $organizationId = $this->postJson(route('api.v1.organizations.store'), ['url' => $url])->json('data.id');

    $this->postJson(route('api.v1.organizations.store'), ['url' => $url])->assertAccepted();

    expect(app('db')->table('sync_runs')->where('organization_id', $organizationId)->count())->toBe(1);
    Queue::assertPushed(SyncOrganizationJob::class, 1);
});
