<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http\Transport;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Нижний слой стека: собственно HTTP-запрос через клиент Laravel — прокси, cookies, заголовки профиля, таймауты.
 */
final readonly class LaravelTransport implements Transport
{
    public function __construct(
        private HttpFactory $http,
        private int $timeoutSeconds,
        private int $connectTimeoutSeconds,
    ) {}

    public function send(ScrapeRequest $request, ScrapeSession $session): Response
    {
        $options = ['cookies' => $session->cookies];

        if ($session->proxy->url !== null) {
            $options['proxy'] = $session->proxy->url;
        }

        $pending = $this->http
            ->withOptions($options)
            ->withHeaders($session->profile->allHeaders())
            ->timeout($this->timeoutSeconds)
            ->connectTimeout($this->connectTimeoutSeconds);

        if (! $request->followRedirects) {
            $pending = $pending->withoutRedirecting();
        }

        try {
            return $pending->get($request->url);
        } catch (ConnectionException $exception) {
            throw SourceUnavailable::because($exception);
        }
    }
}
