<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Scraping\Application\Health\HealthWindow;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Scraping\Application\Health\SourceStatus;
use App\Modules\Shared\Domain\Source\Platform;
use Psr\Log\NullLogger;

function health(?int $pausedFor = null, float $degradedShare = 0.3): SourceHealth
{
    $window = new class implements HealthWindow
    {
        /** @var array<string, list<string>> */
        private array $outcomes = [];

        public function record(Platform $platform, string $outcome): void
        {
            $this->outcomes[$platform->value] = [$outcome, ...($this->outcomes[$platform->value] ?? [])];
        }

        public function recent(Platform $platform): array
        {
            return $this->outcomes[$platform->value] ?? [];
        }
    };

    $availability = new class($pausedFor) implements SourceAvailability
    {
        public function __construct(private readonly ?int $pausedFor) {}

        public function pausedFor(Platform $platform): ?int
        {
            return $this->pausedFor;
        }
    };

    return new SourceHealth($window, $availability, new NullLogger, $degradedShare);
}

it('trusts a platform nothing is known about yet', function (): void {
    expect(health()->statusOf(Platform::Yandex))->toBe(SourceStatus::Healthy);
});

it('stays healthy while failures are rare', function (): void {
    $health = health();

    foreach (range(1, 9) as $_) {
        $health->recordSuccess(Platform::Yandex);
    }
    $health->recordFailure(Platform::Yandex, SourceUnavailable::because());

    expect($health->statusOf(Platform::Yandex))->toBe(SourceStatus::Healthy);
});

it('calls a platform degraded once failures pass the threshold', function (): void {
    $health = health();

    foreach (range(1, 6) as $_) {
        $health->recordSuccess(Platform::Yandex);
    }
    foreach (range(1, 4) as $_) {
        $health->recordFailure(Platform::Yandex, SourceUnavailable::because());
    }

    $report = $health->report()[0];

    expect($report->status)->toBe(SourceStatus::Degraded)
        ->and($report->checks)->toBe(10)
        ->and($report->failures)->toBe(4)
        ->and($report->failureShare)->toBe(0.4)
        ->and($report->lastError)->toBe('source.unavailable');
});

it('treats a single format change as degraded: the parser is already broken', function (): void {
    $health = health();

    foreach (range(1, 20) as $_) {
        $health->recordSuccess(Platform::Yandex);
    }
    $health->recordFailure(Platform::Yandex, SourceDrift::at(DriftStage::ReviewsPage, 'a list of reviews', '{}'));

    expect($health->statusOf(Platform::Yandex))->toBe(SourceStatus::Degraded);
});

it('reports a paused platform apart from a broken one', function (): void {
    $health = health(pausedFor: 900);
    $health->recordSuccess(Platform::Yandex);

    $report = $health->report()[0];

    expect($report->status)->toBe(SourceStatus::Paused)
        ->and($report->pausedForSeconds)->toBe(900)
        ->and($health->overall())->toBe(SourceStatus::Paused);
});
