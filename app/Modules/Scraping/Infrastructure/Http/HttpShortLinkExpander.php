<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use App\Modules\Scraping\Application\Port\ShortLinkExpander;
use App\Modules\Scraping\Domain\Url\SourceUrl;
use App\Modules\Scraping\Infrastructure\Http\Transport\Transport;

/**
 * Проходит редирект короткой ссылки обычным транспортом — значит, на него распространяются те же паузы
 * и та же защита от банов, что и на любой другой запрос.
 */
final readonly class HttpShortLinkExpander implements ShortLinkExpander
{
    public function __construct(
        private Transport $transport,
        private ScrapeSessionFactory $sessions,
    ) {}

    public function expand(SourceUrl $url): ?SourceUrl
    {
        $response = $this->transport->send(new ScrapeRequest($url->toString()), $this->sessions->open());
        $location = $response->header('Location');

        if (! $response->redirect() || $location === '') {
            return null;
        }

        return SourceUrl::tryFrom(str_starts_with($location, '/') ? $url->scheme.'://'.$url->host.$location : $location);
    }
}
