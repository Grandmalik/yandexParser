<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Shared\Domain\Source\Platform;

it('answers an uptime check without a session', function (): void {
    app(SourceHealth::class)->recordSuccess(Platform::Yandex);

    $this->getJson(route('api.v1.health.show'))
        ->assertOk()
        ->assertJsonPath('data.status', 'healthy')
        ->assertJsonPath('data.platforms.0.platform.value', 'yandex')
        ->assertJsonPath('data.platforms.0.checks', 1)
        ->assertJsonPath('data.platforms.0.failures', 0);
});

it('fails the check when the platform changed its format', function (): void {
    app(SourceHealth::class)->recordFailure(
        Platform::Yandex,
        SourceDrift::at(DriftStage::ReviewsPage, 'a list of reviews', '{}'),
    );

    $this->getJson(route('api.v1.health.show'))
        ->assertStatus(503)
        ->assertJsonPath('data.status', 'degraded')
        ->assertJsonPath('data.platforms.0.status.value', 'degraded')
        ->assertJsonPath('data.platforms.0.last_error', 'source.drift');
});

it('names the status in the language of the reader', function (): void {
    app(SourceHealth::class)->recordSuccess(Platform::Yandex);

    $this->getJson(route('api.v1.health.show'))
        ->assertOk()
        ->assertJsonPath('data.platforms.0.status.label', __('scraping.enums.source_status.healthy'));
});
