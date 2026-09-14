<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;
use Throwable;

/**
 * До площадки не достучались или она ответила серверной ошибкой; имеет смысл повторить позже.
 */
final class SourceUnavailable extends DomainException
{
    public static function because(?Throwable $previous = null): self
    {
        return new self(ScrapingErrorCode::Unavailable, previous: $previous);
    }
}
