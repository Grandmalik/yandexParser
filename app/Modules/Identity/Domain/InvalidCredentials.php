<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Shared\Domain\Error\DomainException;

/**
 * Неверная пара email/пароль. Намеренно не сообщает, существует ли такой email.
 */
final class InvalidCredentials extends DomainException
{
    public function __construct()
    {
        parent::__construct(IdentityErrorCode::InvalidCredentials);
    }
}
