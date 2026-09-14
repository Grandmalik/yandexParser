<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Scraping\Infrastructure\Http\ScrapeRequest;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSessionFactory;
use App\Modules\Scraping\Infrastructure\Http\Transport\Transport;
use App\Modules\Scraping\Infrastructure\Persistence\DriftRecorder;
use App\Modules\Shared\Domain\Source\SourceReference;
use Generator;

/**
 * Яндекс Карты через страницу карточки (показатели) и её внутренний API отзывов (сами отзывы), см. ADR-001.
 * Прокси, паузами и банами занимается транспорт (ADR-004).
 */
final readonly class YandexHttpGateway implements ReviewSourceGateway
{
    public function __construct(
        private Transport $transport,
        private ScrapeSessionFactory $sessions,
        private OrganizationPageParser $pageParser,
        private YandexReviewsApi $reviewsApi,
        private YandexResponseMapper $mapper,
        private YandexReviewPages $pages,
        private YandexSettings $settings,
        private DriftRecorder $drifts,
    ) {}

    public function fetchOrganization(SourceReference $source): SourceOrganization
    {
        try {
            $response = $this->transport->send(
                new ScrapeRequest($this->settings->organizationPageUrl($source->externalId), followRedirects: true),
                $this->sessions->open(),
            );

            if ($response->notFound()) {
                throw SourceNotFound::for($source);
            }

            if (! $response->successful()) {
                throw SourceDrift::at(DriftStage::OrganizationPage, 'the card page must answer with HTTP 200', $response->body(), $response->status());
            }

            return $this->mapper->organization($this->pageParser->organization($response->body(), $source));
        } catch (SourceDrift $drift) {
            $this->drifts->record($source, $drift);

            throw $drift;
        }
    }

    /**
     * @return Generator<int, ReviewPage>
     */
    public function fetchReviews(SourceReference $source, int $fromPage = 1): Generator
    {
        $fromPage = max(1, $fromPage);

        try {
            yield from $this->pages->assemble($this->apiPages($source, $fromPage), $source, $fromPage);
        } catch (SourceDrift $drift) {
            $this->drifts->record($source, $drift);

            throw $drift;
        }
    }

    /**
     * Запрашивает страницы по одной и ровно настолько, насколько их вытягивает потребитель. Возобновлённый сбор
     * просит у площадки ту страницу, на которой остановился, а не пролистывает заново уже сохранённое.
     *
     * @return Generator<int, ?PayloadReader>
     */
    private function apiPages(SourceReference $source, int $fromPage): Generator
    {
        $session = $this->reviewsApi->openSession();

        for ($page = $fromPage; ; $page++) {
            yield $this->reviewsApi->page($session, $source->externalId, $page);
        }
    }
}
