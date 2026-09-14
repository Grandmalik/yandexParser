<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

/**
 * Запрос к площадке в терминах транспорта.
 */
final readonly class ScrapeRequest
{
    /**
     * @param  string  $url  Полный URL; строка запроса уходит ровно как есть — от неё зависит подпись.
     */
    public function __construct(
        public string $url,
        public bool $followRedirects = false,
    ) {}

    /**
     * Хост запроса: по нему работают троттлинг и circuit breaker.
     */
    public function host(): string
    {
        return strtolower((string) parse_url($this->url, PHP_URL_HOST));
    }
}
