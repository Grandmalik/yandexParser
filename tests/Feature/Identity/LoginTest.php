<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;

beforeEach(function (): void {
    $this->user = User::factory()->create(['email' => 'owner@example.com']);
    $this->fromSpa();
});

it('logs in with valid credentials and returns the user', function (): void {
    $this->postJson(route('api.v1.auth.login'), ['email' => 'owner@example.com', 'password' => 'password'])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $this->user->id)
        ->assertJsonPath('data.email', 'owner@example.com')
        ->assertJsonMissingPath('data.password');

    $this->assertAuthenticatedAs($this->user, 'web');
});

it('rejects a wrong password without revealing whether the email exists', function (string $email): void {
    $this->postJson(route('api.v1.auth.login'), ['email' => $email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'auth.invalid_credentials')
        ->assertJsonPath('error.message', __('identity.errors.auth.invalid_credentials'));

    $this->assertGuest('web');
})->with(['existing email' => 'owner@example.com', 'unknown email' => 'nobody@example.com']);

it('validates the credentials payload', function (): void {
    $this->postJson(route('api.v1.auth.login'), ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed')
        ->assertJsonStructure(['error' => ['fields' => ['email', 'password']]]);
});

it('throttles repeated login attempts per email and ip', function (): void {
    $this->freezeTime();
    config(['identity.login.max_attempts' => 2]);

    $credentials = ['email' => 'owner@example.com', 'password' => 'wrong-password'];

    $this->postJson(route('api.v1.auth.login'), $credentials)->assertUnprocessable();
    $this->postJson(route('api.v1.auth.login'), $credentials)->assertUnprocessable();

    $this->postJson(route('api.v1.auth.login'), $credentials)
        ->assertTooManyRequests()
        ->assertJsonPath('error.code', 'http.rate_limited')
        ->assertJsonPath('error.details.retry_after', config('identity.login.decay_seconds'));
});
