<?php

declare(strict_types=1);

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Modules\Identity\Application\LogIn;
use App\Modules\Identity\Application\Port\Authenticator;
use App\Modules\Identity\Interfaces\Http\Data\LoginData;
use App\Modules\Identity\Interfaces\Http\Data\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Сессия пользователя: вход и выход.
 */
final readonly class SessionController
{
    /**
     * Вход. Идентификатор сессии пересоздаётся — иначе он годился бы для подмены сессии.
     */
    public function store(LoginData $credentials, Request $request, LogIn $logIn): UserData
    {
        $user = $logIn->handle($credentials->email, $credentials->password, $credentials->remember);

        $request->session()->regenerate();

        return UserData::from($user);
    }

    /**
     * Выход: гасит сессию и её CSRF-токен.
     */
    public function destroy(Request $request, Authenticator $authenticator): Response
    {
        $authenticator->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
