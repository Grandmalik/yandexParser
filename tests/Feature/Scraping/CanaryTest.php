<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Canary;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Scraping\Application\Health\SourceStatus;
use App\Modules\Shared\Domain\Source\Platform;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

/*
| The canary reads one known card per platform the way a collection would, so a format change is noticed on a
| schedule instead of on the next user request. Helper names here are local: shared ones live in YandexHttpGatewayTest.
*/
const CANARY_URL = 'https://yandex.ru/maps/org/1703836794/';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['scraping.platforms.yandex.canary_url' => CANARY_URL]);
});

function cardPage(string $body, int $status = 200): GuzzleHttp\Promise\PromiseInterface
{
    return Http::response($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
}

function platformServing(string $card): void
{
    Http::fake([
        'https://yandex.ru/maps/api/business/fetchReviews*' => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(recordedJson('Yandex/reviews_page_1.json')),
        'https://yandex.ru/maps/org/*' => cardPage($card),
    ]);
}

it('reads the reference card and the first page of its reviews', function (): void {
    platformServing(recordedResponse('Yandex/organization_page.html'));

    $result = app(Canary::class)->check(CANARY_URL);

    expect($result->passed)->toBeTrue()
        ->and($result->source)->toBe('yandex:1703836794')
        ->and($result->name)->toBe('Вольт 11')
        ->and($result->reviewsReported)->toBe(191)
        ->and($result->reviewsOnFirstPage)->toBe(50)
        ->and($result->readReviews())->toBeTrue()
        ->and($result->errorCode)->toBeNull();
});

it('succeeds as a command and reports what it read', function (): void {
    platformServing(recordedResponse('Yandex/organization_page.html'));

    $this->artisan('scraping:canary')
        ->expectsOutputToContain('Вольт 11')
        ->assertSuccessful();
});

it('refuses to run when a configured platform has no reference card', function (): void {
    config(['scraping.platforms.yandex.canary_url' => null]);

    // Пропусти мы такую площадку — она продолжила бы докладывать «healthy», хотя её никто ни разу не прочитал.
    expect(fn (): int => Artisan::call('scraping:canary'))
        ->toThrow(UnexpectedValueException::class, 'scraping.platforms.yandex.canary_url');
});

it('fails when the platform changed its format, and keeps the response for investigation', function (): void {
    Http::fake(['https://yandex.ru/maps/org/*' => cardPage('<html><body><div id="root"></div></body></html>')]);

    $result = app(Canary::class)->check(CANARY_URL);

    expect($result->passed)->toBeFalse()->and($result->errorCode)->toBe('source.drift');
    $this->assertDatabaseHas('source_payloads', ['platform' => 'yandex', 'kind' => 'organization_page']);
});

it('makes a broken source visible in the health endpoint', function (): void {
    Http::fake(['https://yandex.ru/maps/org/*' => cardPage('<html><body><div id="root"></div></body></html>')]);

    $this->artisan('scraping:canary')->assertFailed();

    expect(app(SourceHealth::class)->statusOf(Platform::Yandex))->toBe(SourceStatus::Degraded);
    $this->getJson(route('api.v1.health.show'))->assertStatus(503);
});

it('reports a ban as a failed check rather than a crash', function (): void {
    Http::fake(['https://yandex.ru/maps/org/*' => cardPage('<html><body><div class="SmartCaptcha"></div></body></html>')]);

    $result = app(Canary::class)->check(CANARY_URL);

    expect($result->passed)->toBeFalse()->and($result->errorCode)->toBe('source.blocked');
});

it('checks the card given on the command line instead of the configured ones', function (): void {
    platformServing(recordedResponse('Yandex/organization_page.html'));

    $this->artisan('scraping:canary', ['--url' => 'https://yandex.ru/maps/org/kofeynya/9999999999/'])
        ->assertSuccessful();

    Http::assertSent(fn (Illuminate\Http\Client\Request $request): bool => str_contains($request->url(), '9999999999'));
});
