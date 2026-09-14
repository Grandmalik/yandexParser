<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Errors;

/**
 * Коды ошибок уровня транспорта — тех, что порождает не домен модуля, а сам HTTP-слой.
 */
enum ApiErrorCode: string
{
    case ValidationFailed = 'validation.failed';
    case Unauthenticated = 'auth.unauthenticated';
    case Forbidden = 'auth.forbidden';
    case SessionExpired = 'auth.session_expired';
    case NotFound = 'resource.not_found';
    case MethodNotAllowed = 'http.method_not_allowed';
    case RateLimited = 'http.rate_limited';
    case HttpError = 'http.error';
    case Internal = 'internal';

    /**
     * HTTP-статус → код ошибки.
     */
    public static function fromStatus(int $status): self
    {
        return match ($status) {
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::NotFound,
            405 => self::MethodNotAllowed,
            419 => self::SessionExpired,
            422 => self::ValidationFailed,
            429 => self::RateLimited,
            default => $status >= 500 ? self::Internal : self::HttpError,
        };
    }

    /**
     * Ключ перевода сообщения для этого кода.
     */
    public function messageKey(): string
    {
        return 'shared.errors.'.$this->value;
    }
}
