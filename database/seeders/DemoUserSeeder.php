<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Database\Seeder;

/**
 * Единственный пользователь приложения — регистрации нет. Повторный запуск обновляет его на месте,
 * а не заводит второго.
 */
final class DemoUserSeeder extends Seeder
{
    public const string EMAIL = 'demo@example.com';

    public const string PASSWORD = 'password';

    private const string NAME = 'Demo';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            ['name' => self::NAME, 'password' => self::PASSWORD],
        );
    }
}
