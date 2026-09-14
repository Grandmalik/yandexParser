<?php

declare(strict_types=1);

use App\Modules\Scraping\Infrastructure\Resilience\RequestThrottle;
use Carbon\CarbonInterval;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Sleep;
use Tests\Support\TestClock;

beforeEach(function (): void {
    $this->clock = new TestClock;
    $this->slept = [];

    Sleep::fake();
    // Пока идёт пауза, время двигается — ровно так же, как это происходит в настоящем воркере.
    Sleep::whenFakingSleep(function (CarbonInterval $duration): void {
        $milliseconds = (int) $duration->totalMilliseconds;
        $this->slept[] = $milliseconds;
        $this->clock->advance($milliseconds);
    });

    $this->throttle = new RequestThrottle(
        new Repository(new ArrayStore),
        $this->clock,
        minIntervalMs: 1000,
        jitterMs: 0,
        lockSeconds: 5,
    );
});

afterEach(function (): void {
    Sleep::fake(false);
});

it('lets the first request through and spaces the next ones', function (): void {
    $this->throttle->await('yandex.ru|direct');
    $this->throttle->await('yandex.ru|direct');
    $this->throttle->await('yandex.ru|direct');

    expect($this->slept)->toBe([1000, 1000]);
});

it('only waits for the rest of the interval', function (): void {
    $this->throttle->await('yandex.ru|direct');
    $this->clock->advance(400);
    $this->throttle->await('yandex.ru|direct');

    expect($this->slept)->toBe([600]);
});

it('does not wait once the interval has passed', function (): void {
    $this->throttle->await('yandex.ru|direct');
    $this->clock->advance(5000);
    $this->throttle->await('yandex.ru|direct');

    expect($this->slept)->toBe([]);
});

it('throttles every route independently', function (): void {
    $this->throttle->await('yandex.ru|proxy-a');
    $this->throttle->await('yandex.ru|proxy-b');

    expect($this->slept)->toBe([]);
});

it('adds random jitter within the configured bound', function (): void {
    $throttle = new RequestThrottle(new Repository(new ArrayStore), $this->clock, minIntervalMs: 1000, jitterMs: 500, lockSeconds: 5);

    foreach (range(1, 20) as $_) {
        $throttle->await('yandex.ru|direct');
    }

    expect($this->slept)->each->toBeGreaterThanOrEqual(1000)->toBeLessThanOrEqual(1500);
});
