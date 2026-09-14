<?php

declare(strict_types=1);

namespace App\Modules\Identity\Interfaces\Http\Controllers;

use App\Modules\Identity\Application\Port\Authenticator;
use App\Modules\Identity\Interfaces\Http\Data\UserData;
use Illuminate\Auth\AuthenticationException;

/**
 * Отдаёт пользователя текущей сессии; без сессии — 401.
 */
final readonly class CurrentUserController
{
    public function __invoke(Authenticator $authenticator): UserData
    {
        return UserData::from($authenticator->current() ?? throw new AuthenticationException);
    }
}
