<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Shared\Domain\Error\ErrorCode;
use App\Modules\Shared\Domain\Error\ErrorKind;

enum IdentityErrorCode: string implements ErrorCode
{
    case InvalidCredentials = 'auth.invalid_credentials';

    public function code(): string
    {
        return $this->value;
    }

    public function kind(): ErrorKind
    {
        return match ($this) {
            // Отклонённый вход — ошибка формы, а не отсутствие сессии: клиент не должен принять её за «разлогинило».
            self::InvalidCredentials => ErrorKind::Validation,
        };
    }

    public function messageKey(): string
    {
        return 'identity.errors.'.$this->value;
    }
}
