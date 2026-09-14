<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

/**
 * Пользователь в терминах приложения — то, что Application-слой отдаёт наружу.
 */
final readonly class UserView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
