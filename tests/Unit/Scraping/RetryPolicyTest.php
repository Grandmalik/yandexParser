<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\RetryPolicy;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;

beforeEach(function (): void {
    $this->policy = new RetryPolicy(baseSeconds: 30, maxSeconds: 3600, blockedBaseSeconds: 600);
});

it('does not retry failures a retry cannot fix', function (Throwable $failure): void {
    expect($this->policy->delayAfter($failure, 1))->toBeNull();
})->with([
    'source changed' => fn () => SourceDrift::at(DriftStage::ReviewsPage, 'x', ''),
    'organization gone' => fn () => SourceNotFound::for(new SourceReference(Platform::Yandex, '1234567')),
    'invalid link' => fn () => InvalidSourceUrl::malformed(),
]);

it('backs off exponentially with jitter, never immediately', function (int $attempt, int $min, int $max): void {
    foreach (range(1, 50) as $_) {
        expect($this->policy->delayAfter(SourceUnavailable::because(), $attempt))->toBeGreaterThanOrEqual($min)->toBeLessThanOrEqual($max);
    }
})->with([
    'first retry' => [1, 15, 30],
    'third retry' => [3, 60, 120],
    'capped' => [20, 1800, 3600],
]);

it('waits longer after a ban', function (): void {
    expect($this->policy->delayAfter(SourceBlocked::byCaptcha(), 1))->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(600);
});

it('never retries earlier than the platform asked', function (Throwable $failure): void {
    expect($this->policy->delayAfter($failure, 1))->toBe(2000);
})->with([
    'throttled' => fn () => SourceRateLimited::retryAfter(2000),
    'circuit open' => fn () => SourceBlocked::circuitOpen(2000),
]);

it('retries unexpected infrastructure failures', function (): void {
    expect($this->policy->delayAfter(new RuntimeException('deadlock'), 1))->toBeGreaterThanOrEqual(15);
});
