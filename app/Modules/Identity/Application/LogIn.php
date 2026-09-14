<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application;

use App\Modules\Identity\Application\Port\Authenticator;
use App\Modules\Identity\Domain\InvalidCredentials;

/**
 * Вход пользователя.
 */
final readonly class LogIn
{
    public function __construct(private Authenticator $authenticator) {}

    /**
     * Проверяет пару email/пароль и открывает сессию.
     *
     * @throws InvalidCredentials
     */
    public function handle(string $email, string $password, bool $remember): UserView
    {
        return $this->authenticator->attempt($email, $password, $remember) ?? throw new InvalidCredentials;
    }
}
