<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http\Transport;

use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use App\Modules\Scraping\Infrastructure\Resilience\BanProtection;
use Illuminate\Http\Client\Response;

/**
 * Верхний слой стека: не пускает запрос к хосту, который стоит на паузе, и отправляет маршрут в карантин,
 * если площадка ответила баном.
 */
final readonly class BanAwareTransport implements Transport
{
    public function __construct(
        private Transport $inner,
        private BanProtection $protection,
    ) {}

    public function send(ScrapeRequest $request, ScrapeSession $session): Response
    {
        return $this->protection->call($request->host(), $session, fn (): Response => $this->inner->send($request, $session));
    }
}
