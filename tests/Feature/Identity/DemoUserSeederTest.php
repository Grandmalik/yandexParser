<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Hash;

it('creates the single user and does not duplicate it on re-run', function (): void {
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);

    $user = User::query()->sole();

    expect($user->email)->toBe(DemoUserSeeder::EMAIL)
        ->and(Hash::check(DemoUserSeeder::PASSWORD, $user->password))->toBeTrue();
});
