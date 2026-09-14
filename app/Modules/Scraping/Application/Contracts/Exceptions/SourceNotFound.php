<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Площадка не знает такой организации: карточки нет или её удалили.
 */
final class SourceNotFound extends DomainException
{
    /**
     * Ошибка с адресом карточки в деталях.
     */
    public static function for(SourceReference $source): self
    {
        return new self(ScrapingErrorCode::NotFound, details: ['source' => $source->toString()]);
    }
}
