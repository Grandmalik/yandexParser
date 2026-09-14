<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

/**
 * Id пользователя, стоящего за запросом, — для модулей, которые ссылаются на пользователя только по id.
 */
final class AuthenticatedUser
{
    /**
     * Достаёт id из текущей сессии; если его нет или он не похож на число — 401, а не «пользователь 0».
     *
     * @throws AuthenticationException
     */
    public static function id(Request $request): int
    {
        $id = $request->user()?->getAuthIdentifier();

        if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
            throw new AuthenticationException;
        }

        return (int) $id;
    }
}
