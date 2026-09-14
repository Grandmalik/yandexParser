<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http\Transport;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use Illuminate\Http\Client\Response;

/**
 * Каждый запрос к площадке идёт через стек транспорта (ai/decisions/ADR-004):
 * защита от банов → троттлинг → охранник ответа → HTTP. Адаптеры о прокси, паузах и банах не знают вовсе.
 */
interface Transport
{
    /**
     * Отправляет запрос в рамках сессии и возвращает ответ.
     *
     * @throws SourceUnavailable
     * @throws SourceRateLimited
     * @throws SourceBlocked
     */
    public function send(ScrapeRequest $request, ScrapeSession $session): Response;
}
