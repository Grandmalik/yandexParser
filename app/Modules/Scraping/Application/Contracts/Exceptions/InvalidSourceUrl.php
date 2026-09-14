<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;

/**
 * Ссылка не годится. Это ошибка поля формы, поэтому она показывается рядом с полем, а не как сбой сервиса.
 */
final class InvalidSourceUrl extends DomainException
{
    /**
     * Строка вообще не похожа на ссылку.
     */
    public static function malformed(): self
    {
        return new self(ScrapingErrorCode::InvalidUrl);
    }

    /**
     * Хост не принадлежит ни одной известной площадке.
     */
    public static function unsupportedHost(string $host): self
    {
        return new self(ScrapingErrorCode::UnsupportedHost, details: ['host' => $host], messageParameters: ['host' => $host]);
    }

    /**
     * Ссылка на площадку есть, но ведёт не на карточку организации.
     */
    public static function notAnOrganization(): self
    {
        return new self(ScrapingErrorCode::NotAnOrganization);
    }
}
