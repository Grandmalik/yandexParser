<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Error;

use RuntimeException;
use Throwable;

/**
 * Основа любой бизнес-ошибки. Несёт {@see ErrorCode} вместо произвольного текста, поэтому слой доставки
 * отдаёт стабильный код и переведённое сообщение, а не строку из глубины кода.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details  Структурированные данные, которые можно показать клиенту API.
     * @param  array<string, scalar>  $messageParameters  Подстановки в переведённое сообщение.
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly array $details = [],
        public readonly array $messageParameters = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($errorCode->code(), previous: $previous);
    }

    /**
     * Хук отчётности Laravel: true — ошибка считается обработанной (ожидаемые ошибки клиента не засоряют лог
     * сбоев), false — репортится штатно, как настоящий сбой.
     */
    public function report(): bool
    {
        return $this->errorCode->kind()->isClientError();
    }

    /**
     * Контекст для записи в лог: код ошибки и её детали.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['error_code' => $this->errorCode->code(), ...$this->details];
    }
}
