<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use Carbon\CarbonInterval;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const CARD_PAGE = 'https://yandex.ru/maps/org/*';
const CAPTCHA_PAGE = '<html><body><div class="SmartCaptcha"></div></body></html>';

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->source = new SourceReference(Platform::Yandex, '1703836794');
});

function gateway(): ReviewSourceGateway
{
    return app(ReviewSourceGateway::class);
}

function attempt(Closure $call): ?Throwable
{
    try {
        $call();
    } catch (Throwable $failure) {
        return $failure;
    }

    return null;
}

it('quarantines the route that got a captcha', function (): void {
    Http::fake([CARD_PAGE => Http::response(CAPTCHA_PAGE, 200, ['Content-Type' => 'text/html'])]);

    expect(attempt(fn () => gateway()->fetchOrganization($this->source)))->toBeInstanceOf(SourceBlocked::class);

    // Единственный маршрут (собственный IP сервера) в карантине: запрос не делается вовсе.
    expect(fn () => gateway()->fetchOrganization($this->source))
        ->toThrow(fn (SourceBlocked $blocked) => expect($blocked->retryAfterSeconds)->toBe(config('scraping.proxies.ban_seconds')));
    Http::assertSentCount(1);
});

it('pauses the whole host after repeated bans even when fresh routes remain', function (): void {
    config([
        'scraping.proxies.urls' => array_map(fn (int $i): string => "http://10.0.0.{$i}:8080", range(1, 4)),
        'scraping.circuit_breaker.failure_threshold' => 3,
    ]);
    Http::fake([CARD_PAGE => Http::response(CAPTCHA_PAGE, 200, ['Content-Type' => 'text/html'])]);

    foreach (range(1, 3) as $_) {
        expect(attempt(fn () => gateway()->fetchOrganization($this->source)))->toBeInstanceOf(SourceBlocked::class);
    }

    expect(fn () => gateway()->fetchOrganization($this->source))
        ->toThrow(fn (SourceBlocked $blocked) => expect($blocked->retryAfterSeconds)->toBe(config('scraping.circuit_breaker.open_seconds')));
    Http::assertSentCount(3);
});

it('cools a throttled route down for at least what the platform asked', function (): void {
    Http::fake([CARD_PAGE => Http::response('', 429, ['Retry-After' => '1000'])]);

    expect(attempt(fn () => gateway()->fetchOrganization($this->source)))->toBeInstanceOf(SourceRateLimited::class);
    expect(fn () => gateway()->fetchOrganization($this->source))
        ->toThrow(fn (SourceBlocked $blocked) => expect($blocked->retryAfterSeconds)->toBe(1000));
});

it('spaces requests to the platform', function (): void {
    config(['scraping.throttle.min_interval_ms' => 1000, 'scraping.throttle.jitter_ms' => 0]);
    Sleep::fake();
    Http::fake([CARD_PAGE => Http::response(recordedResponse('Yandex/organization_page.html'), 200, ['Content-Type' => 'text/html'])]);

    gateway()->fetchOrganization($this->source);
    gateway()->fetchOrganization($this->source);

    Sleep::assertSleptTimes(1);
    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMilliseconds > 900 && $duration->totalMilliseconds <= 1000);
});

it('keeps one browser identity for all requests of a session', function (): void {
    Http::fake(['https://yandex.ru/maps/api/business/fetchReviews*' => Http::sequence()
        ->push(recordedJson('Yandex/reviews_token.json'))
        ->push(recordedJson('Yandex/reviews_page_1.json'))
        ->push(recordedJson('Yandex/reviews_page_2.json'))
        ->push(recordedJson('Yandex/reviews_page_3.json'))
        ->push(recordedJson('Yandex/reviews_page_4.json')),
    ]);

    iterator_to_array(gateway()->fetchReviews($this->source), false);

    $userAgents = array_unique(array_map(
        fn (array $exchange): string => $exchange[0]->header('User-Agent')[0] ?? '',
        Http::recorded()->all(),
    ));
    $configured = array_column(config('scraping.browser_profiles'), 'user_agent');

    expect($userAgents)->toHaveCount(1)
        ->and($configured)->toContain(array_values($userAgents)[0]);
    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Accept-Language'));
});
