<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use LogicException;

/**
 * Сессия обхода плюс её CSRF-токен: сам по себе токен бесполезен — он действителен только вместе с cookies
 * этой же сессии.
 */
final class YandexSession
{
    private ?string $token = null;

    public function __construct(public readonly ScrapeSession $http) {}

    /**
     * Текущий токен; обращение до его получения — ошибка в коде.
     */
    public function token(): string
    {
        return $this->token ?? throw new LogicException('The session has no CSRF token yet.');
    }

    /**
     * Запоминает выданный площадкой токен.
     */
    public function renew(string $token): void
    {
        $this->token = $token;
    }
}
