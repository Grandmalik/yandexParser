<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;

beforeEach(function (): void {
    $this->fromSpa();
});

it('returns the current user of the session', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.v1.auth.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', $user->name);
});

it('requires a session for the current user', function (): void {
    $this->getJson(route('api.v1.auth.me'))
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'auth.unauthenticated');
});

it('logs out and ends the session', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.v1.auth.logout'))
        ->assertNoContent();

    $this->assertGuest('web');
});

it('refuses logout without a session', function (): void {
    $this->postJson(route('api.v1.auth.logout'))->assertUnauthorized();
});
