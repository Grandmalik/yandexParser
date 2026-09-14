<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Shared\Domain\Error\ErrorCode;
use App\Modules\Shared\Domain\Error\ErrorKind;

/**
 * Коды ошибок при работе с площадками.
 */
enum ScrapingErrorCode: string implements ErrorCode
{
    case InvalidUrl = 'source.invalid_url';
    case UnsupportedHost = 'source.unsupported_host';
    case NotAnOrganization = 'source.not_an_organization';
    case NotFound = 'source.not_found';
    case Unavailable = 'source.unavailable';
    case RateLimited = 'source.rate_limited';
    /** Вместо данных площадка отдаёт капчу или бан-страницу. */
    case Blocked = 'source.blocked';
    /** Площадка сменила разметку или API: ответы больше не совпадают с тем, чего ждёт парсер. */
    case Drift = 'source.drift';
    /** Отзывов собрано меньше, чем площадка заявляет доступными. */
    case Partial = 'source.partial';

    public function code(): string
    {
        return $this->value;
    }

    public function kind(): ErrorKind
    {
        return match ($this) {
            self::InvalidUrl, self::UnsupportedHost, self::NotAnOrganization => ErrorKind::Validation,
            self::NotFound => ErrorKind::NotFound,
            self::RateLimited => ErrorKind::RateLimited,
            self::Unavailable, self::Blocked, self::Partial => ErrorKind::Unavailable,
            self::Drift => ErrorKind::Internal,
        };
    }

    public function messageKey(): string
    {
        return 'scraping.errors.'.$this->value;
    }
}
