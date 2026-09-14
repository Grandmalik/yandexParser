<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use GuzzleHttp\Cookie\CookieJar;

/**
 * Один «посетитель»: маршрут выхода, личность браузера и cookies остаются одними и теми же для всех его запросов.
 */
final readonly class ScrapeSession
{
    public function __construct(
        public Proxy $proxy,
        public BrowserProfile $profile,
        public CookieJar $cookies = new CookieJar,
    ) {}
}
