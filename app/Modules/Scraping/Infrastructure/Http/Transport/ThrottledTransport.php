<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http\Transport;

use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use App\Modules\Scraping\Infrastructure\Resilience\RequestThrottle;
use Illuminate\Http\Client\Response;

/**
 * Разводит во времени запросы к хосту с одного маршрута (IP): площадка видит человеческий темп от посетителя.
 */
final readonly class ThrottledTransport implements Transport
{
    public function __construct(
        private Transport $inner,
        private RequestThrottle $throttle,
    ) {}

    public function send(ScrapeRequest $request, ScrapeSession $session): Response
    {
        $this->throttle->await($request->host().'|'.$session->proxy->id);

        return $this->inner->send($request, $session);
    }
}
