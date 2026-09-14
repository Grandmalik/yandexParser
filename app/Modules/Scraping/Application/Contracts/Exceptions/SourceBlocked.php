<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;

/**
 * Площадка распознала в нас бота (капча или бан-страница) — либо мы сами перестали к ней обращаться после таких
 * сигналов. Повторять имеет смысл позже и желательно с другого IP.
 */
final class SourceBlocked extends DomainException
{
    private function __construct(public readonly ?int $retryAfterSeconds)
    {
        parent::__construct(ScrapingErrorCode::Blocked, details: ['retry_after' => $retryAfterSeconds]);
    }

    /**
     * В ответ пришла капча.
     */
    public static function byCaptcha(): self
    {
        return new self(null);
    }

    /**
     * Запросы к хосту приостановлены после повторных банов (circuit breaker открыт).
     */
    public static function circuitOpen(int $retryAfterSeconds): self
    {
        return new self($retryAfterSeconds);
    }

    /**
     * Все маршруты выхода в карантине — идти к площадке не с чего.
     */
    public static function allProxiesBanned(int $retryAfterSeconds): self
    {
        return new self($retryAfterSeconds);
    }
}
