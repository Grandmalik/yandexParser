<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;

/**
 * Пул маршрутов выхода к площадкам.
 */
interface ProxyPool
{
    /**
     * Наиболее давно использованный маршрут из тех, что сейчас не в карантине после бана.
     *
     * @throws SourceBlocked когда все маршруты забанены
     */
    public function acquire(): Proxy;

    /**
     * Выводит маршрут из ротации на указанное время (капча, троттлинг).
     */
    public function ban(Proxy $proxy, int $seconds): void;
}
