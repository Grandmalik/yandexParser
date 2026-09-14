<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Error;

/**
 * Категория бизнес-ошибки, не зависящая от транспорта; слой доставки переводит её в HTTP-статус.
 */
enum ErrorKind
{
    case Validation;
    case Unauthenticated;
    case Forbidden;
    case NotFound;
    case Conflict;
    case RateLimited;
    case Unavailable;
    case Internal;

    /**
     * Ожидаемый исход из-за данных или состояния клиента, а не сбой системы.
     */
    public function isClientError(): bool
    {
        return match ($this) {
            self::Unavailable, self::Internal => false,
            default => true,
        };
    }
}
