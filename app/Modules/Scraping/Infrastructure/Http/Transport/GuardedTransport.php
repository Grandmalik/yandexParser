<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http\Transport;

use App\Modules\Scraping\Infrastructure\Http\ResponseGuard;
use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use Illuminate\Http\Client\Response;

/**
 * Пропускает ответ через охранника: капча, троттлинг и серверные ошибки становятся ошибками с кодом,
 * а не мусором, который поедет дальше в парсер.
 */
final readonly class GuardedTransport implements Transport
{
    public function __construct(
        private Transport $inner,
        private ResponseGuard $guard,
    ) {}

    public function send(ScrapeRequest $request, ScrapeSession $session): Response
    {
        $response = $this->inner->send($request, $session);

        $this->guard->inspect($response);

        return $response;
    }
}
