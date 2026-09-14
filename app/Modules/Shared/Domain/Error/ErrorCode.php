<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Error;

/**
 * Машиночитаемый идентификатор ошибки. Каждый модуль объявляет свои коды backed-энумом с этим контрактом.
 */
interface ErrorCode
{
    /**
     * Стабильный идентификатор через точку, который видит клиент API, например «source.not_found».
     */
    public function code(): string;

    /**
     * Категория ошибки — из неё в одном месте выводится HTTP-статус.
     */
    public function kind(): ErrorKind;

    /**
     * Ключ перевода человекочитаемого сообщения, например «scraping.errors.source.not_found».
     */
    public function messageKey(): string;
}
