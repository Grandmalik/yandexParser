<?php

declare(strict_types=1);

use App\Modules\Scraping\Infrastructure\Resilience\CircuitBreaker;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\Support\TestClock;

beforeEach(function (): void {
    $this->clock = new TestClock;
    $this->breaker = new CircuitBreaker(new Repository(new ArrayStore), $this->clock, failureThreshold: 3, openSeconds: 900);
});

it('stays closed below the failure threshold', function (): void {
    $this->breaker->recordFailure('yandex.ru');
    $this->breaker->recordFailure('yandex.ru');

    expect($this->breaker->retryAfter('yandex.ru'))->toBeNull();
});

it('opens after consecutive failures and tells how long to wait', function (): void {
    foreach (range(1, 3) as $_) {
        $this->breaker->recordFailure('yandex.ru');
    }

    expect($this->breaker->retryAfter('yandex.ru'))->toBe(900)
        ->and($this->breaker->retryAfter('example.com'))->toBeNull();

    $this->clock->advance(600_000);

    expect($this->breaker->retryAfter('yandex.ru'))->toBe(300);
});

it('lets a trial request through when the pause is over and reopens on another failure', function (): void {
    foreach (range(1, 3) as $_) {
        $this->breaker->recordFailure('yandex.ru');
    }

    $this->clock->advance(901_000);
    expect($this->breaker->retryAfter('yandex.ru'))->toBeNull();

    $this->breaker->recordFailure('yandex.ru');
    expect($this->breaker->retryAfter('yandex.ru'))->toBe(900);
});

it('closes and forgets failures after a success', function (): void {
    foreach (range(1, 3) as $_) {
        $this->breaker->recordFailure('yandex.ru');
    }

    $this->breaker->recordSuccess('yandex.ru');
    $this->breaker->recordFailure('yandex.ru');

    expect($this->breaker->retryAfter('yandex.ru'))->toBeNull();
});
