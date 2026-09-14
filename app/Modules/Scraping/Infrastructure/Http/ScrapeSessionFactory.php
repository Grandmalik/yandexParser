<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;

/**
 * Открывает сессии обхода: берёт свободный маршрут и профиль браузера.
 */
final readonly class ScrapeSessionFactory
{
    public function __construct(
        private ProxyPool $proxies,
        private BrowserProfiles $profiles,
    ) {}

    /**
     * Новая сессия.
     *
     * @throws SourceBlocked когда все маршруты забанены
     */
    public function open(): ScrapeSession
    {
        return new ScrapeSession($this->proxies->acquire(), $this->profiles->pick());
    }
}
