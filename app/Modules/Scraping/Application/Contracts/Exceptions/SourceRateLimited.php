<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;

/**
 * Площадка попросила снизить темп (429). Повтор возможен, но не раньше, чем она разрешила.
 */
final class SourceRateLimited extends DomainException
{
    private function __construct(public readonly ?int $retryAfterSeconds)
    {
        parent::__construct(ScrapingErrorCode::RateLimited, details: ['retry_after' => $retryAfterSeconds]);
    }

    /**
     * Ошибка с паузой из заголовка `Retry-After`, если площадка её назвала.
     */
    public static function retryAfter(?int $seconds): self
    {
        return new self($seconds);
    }
}
