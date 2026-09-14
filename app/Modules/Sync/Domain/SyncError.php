<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

use App\Modules\Shared\Domain\Error\ErrorCode;

/**
 * Почему сбор закончился плохо. Хранится ключ перевода, а не готовый текст: сообщение собирается в момент
 * чтения и на языке читателя, а не на языке воркера, который упал.
 */
final readonly class SyncError
{
    public function __construct(
        public string $code,
        public string $messageKey,
    ) {}

    /**
     * Ошибка прогона из доменного кода ошибки.
     */
    public static function fromErrorCode(ErrorCode $error): self
    {
        return new self($error->code(), $error->messageKey());
    }
}
