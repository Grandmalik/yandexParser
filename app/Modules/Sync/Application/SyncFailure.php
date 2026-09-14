<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Error\ErrorCode;
use App\Modules\Sync\Application\Contracts\SyncErrorCode;
use Throwable;

/**
 * Превращает исключение в код ошибки прогона.
 */
final class SyncFailure
{
    /**
     * У бизнес-ошибок есть собственный код (источник недоступен, забанил, сменил формат…); всё остальное —
     * наша собственная поломка.
     */
    public static function codeOf(Throwable $failure): ErrorCode
    {
        return $failure instanceof DomainException ? $failure->errorCode : SyncErrorCode::Internal;
    }
}
