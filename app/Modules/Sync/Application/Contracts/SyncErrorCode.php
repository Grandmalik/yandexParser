<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts;

use App\Modules\Shared\Domain\Error\ErrorCode;
use App\Modules\Shared\Domain\Error\ErrorKind;

/**
 * Коды ошибок конвейера сбора.
 */
enum SyncErrorCode: string implements ErrorCode
{
    case AlreadyRunning = 'sync.already_running';
    /** Непредвиденный сбой нашего кода или инфраструктуры, а не площадки. */
    case Internal = 'sync.internal';

    public function code(): string
    {
        return $this->value;
    }

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::AlreadyRunning => ErrorKind::Conflict,
            self::Internal => ErrorKind::Internal,
        };
    }

    public function messageKey(): string
    {
        return 'sync.errors.'.$this->value;
    }
}
