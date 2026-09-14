<?php

declare(strict_types=1);

it('publishes the application section without authentication', function (): void {
    $this->getJson(route('api.v1.app-config.show'))
        ->assertOk()
        ->assertJsonPath('data.application.name', config('app.name'))
        ->assertJsonPath('data.application.locale', app()->getLocale());
});

it('publishes how to reach the websocket server, disabled when there is none', function (): void {
    $this->getJson(route('api.v1.app-config.show'))
        ->assertOk()
        ->assertJsonPath('data.broadcasting.driver', 'null')
        // Нет ключа — слушать некому: интерфейс переходит на опрос состояния через API.
        ->assertJsonPath('data.broadcasting.enabled', false)
        ->assertJsonPath('data.broadcasting.auth_endpoint', '/broadcasting/auth');
});

it('publishes what clients must not hardcode: polling interval and status labels', function (): void {
    $response = $this->getJson(route('api.v1.app-config.show'))
        ->assertOk()
        ->assertJsonPath('data.sync.polling_interval_ms', config('sync.polling_interval_ms'));

    expect($response->json('data.sync.statuses'))
        ->toContain(['value' => 'running', 'label' => __('sync.enums.status.running')])
        ->toHaveCount(5);
});
