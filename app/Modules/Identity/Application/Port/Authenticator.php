<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Port;

use App\Modules\Identity\Application\UserView;

/**
 * Порт аутентификации: приложение не знает, чем именно она сделана.
 */
interface Authenticator
{
    /**
     * Открывает сессию по паре email/пароль; null — если пара не подошла.
     */
    public function attempt(string $email, string $password, bool $remember): ?UserView;

    /**
     * Пользователь текущей сессии или null.
     */
    public function current(): ?UserView;

    /**
     * Завершает сессию.
     */
    public function logout(): void;
}
