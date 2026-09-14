<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Application\Contracts\Events\OrganizationConnected;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

const CARD_URL = 'https://yandex.ru/maps/org/kofeynya_tsentr/1234567890/reviews/';

beforeEach(function (): void {
    Http::preventStrayRequests();
    Event::fake([OrganizationConnected::class]);

    $this->user = User::factory()->create();
    $this->actingAs($this->user)->fromSpa();
});

function connect(string $url): Illuminate\Testing\TestResponse
{
    return test()->postJson(route('api.v1.organizations.store'), ['url' => $url]);
}

it('connects an organization by its card link and requests data collection', function (): void {
    $response = connect(CARD_URL)
        ->assertAccepted()
        ->assertJsonPath('data.external_id', '1234567890')
        ->assertJsonPath('data.platform', ['value' => 'yandex', 'label' => __('shared.enums.platform.yandex')])
        ->assertJsonPath('data.source_url', 'https://yandex.ru/maps/org/1234567890/')
        ->assertJsonPath('data.rating_summary', null)
        ->assertJsonPath('data.metrics_updated_at', null);

    $organizationId = $response->json('data.id');

    $this->assertDatabaseHas('organization_user', ['organization_id' => $organizationId, 'user_id' => $this->user->id]);
    Event::assertDispatched(
        OrganizationConnected::class,
        fn (OrganizationConnected $event): bool => $event->organizationId === $organizationId && $event->userId === $this->user->id,
    );
});

it('reuses the organization when the card is connected again', function (): void {
    $first = connect(CARD_URL)->json('data.id');
    $again = connect('https://yandex.ru/maps/org/1234567890/')->json('data.id');

    $this->actingAs(User::factory()->create());
    $byAnotherUser = connect(CARD_URL)->json('data.id');

    expect($again)->toBe($first)->and($byAnotherUser)->toBe($first);
    $this->assertDatabaseCount('organizations', 1);
    $this->assertDatabaseCount('organization_user', 2);
});

it('expands a short link before recognizing the card', function (): void {
    Http::fake([
        'https://yandex.ru/maps/-/CCUqYHh0dB' => Http::response('', 302, ['Location' => 'https://yandex.ru/maps/org/cafe/1234567890/']),
    ]);

    connect('https://yandex.ru/maps/-/CCUqYHh0dB')
        ->assertAccepted()
        ->assertJsonPath('data.external_id', '1234567890');
});

it('never requests a host outside the allowlist while expanding', function (): void {
    Http::fake([
        'https://yandex.ru/maps/-/*' => Http::response('', 302, ['Location' => 'https://evil.example/maps/-/next']),
    ]);

    connect('https://yandex.ru/maps/-/CCUqYHh0dB')
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.url.0', __('scraping.errors.source.unsupported_host', ['host' => 'evil.example']));

    Http::assertSentCount(1);
});

it('reports a link that is not an organization card next to the field', function (string $url, string $messageKey): void {
    connect($url)
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed')
        ->assertJsonPath('error.fields.url.0', __($messageKey, ['host' => 'google.com']));

    $this->assertDatabaseCount('organizations', 0);
    Event::assertNotDispatched(OrganizationConnected::class);
})->with([
    'not a link' => ['кофейня на тверской', 'scraping.errors.source.invalid_url'],
    'another platform' => ['https://google.com/maps/place/cafe', 'scraping.errors.source.unsupported_host'],
    'map without an organization' => ['https://yandex.ru/maps/213/moscow/', 'scraping.errors.source.not_an_organization'],
]);

it('validates the request body', function (): void {
    $this->postJson(route('api.v1.organizations.store'), [])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['fields' => ['url']]]);
});

it('reports an unavailable platform when a short link cannot be expanded', function (): void {
    Http::fake(['https://yandex.ru/maps/-/*' => Http::response('', 503)]);

    connect('https://yandex.ru/maps/-/CCUqYHh0dB')
        ->assertServiceUnavailable()
        ->assertJsonPath('error.code', 'source.unavailable');
});

it('requires authentication', function (): void {
    auth()->logout();

    connect(CARD_URL)->assertUnauthorized();
});
