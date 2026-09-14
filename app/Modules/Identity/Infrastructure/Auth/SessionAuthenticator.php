<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Auth;

use App\Modules\Identity\Application\Port\Authenticator;
use App\Modules\Identity\Application\UserView;
use App\Modules\Identity\Infrastructure\Persistence\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use LogicException;

/**
 * Аутентификация SPA по cookie-сессии (Sanctum в stateful-режиме поверх session guard).
 */
final readonly class SessionAuthenticator implements Authenticator
{
    public function __construct(private AuthFactory $auth) {}

    public function attempt(string $email, string $password, bool $remember): ?UserView
    {
        $guard = $this->sessionGuard();

        if (! $guard->attempt(['email' => $email, 'password' => $password], $remember)) {
            return null;
        }

        return $this->toView($guard->user());
    }

    /**
     * Текущий пользователь; guard берёт тот, что выбрал middleware маршрута (например, «sanctum»).
     */
    public function current(): ?UserView
    {
        return $this->toView($this->auth->guard()->user());
    }

    public function logout(): void
    {
        $this->sessionGuard()->logout();
    }

    /**
     * Guard сессии из конфигурации; другой тип guard — ошибка конфигурации, а не повод работать молча.
     */
    private function sessionGuard(): StatefulGuard
    {
        $guard = $this->auth->guard(config()->string('identity.guard'));

        if (! $guard instanceof StatefulGuard) {
            throw new LogicException('The identity guard must be a stateful (session) guard.');
        }

        return $guard;
    }

    /**
     * Пользователь Laravel → UserView; чужой тип пользователя — null.
     */
    private function toView(?Authenticatable $user): ?UserView
    {
        if (! $user instanceof User) {
            return null;
        }

        return new UserView(id: $user->id, name: $user->name, email: $user->email);
    }
}
