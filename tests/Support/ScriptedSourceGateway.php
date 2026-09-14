<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Scraping\Application\Contracts\SourceReview;
use App\Modules\Shared\Domain\Source\SourceReference;
use DateTimeImmutable;
use Generator;
use Throwable;

/**
 * Площадка, которая возвращает ровно то, что подготовил тест: тесты конвейера не зависят ни от HTTP, ни от фикстур.
 */
final class ScriptedSourceGateway implements ReviewSourceGateway
{
    public int $organizationCalls = 0;

    public int $deliveredPages = 0;

    /** С какой страницы попросил начать потребитель — по нему тест доказывает, что повтор продолжил с места обрыва. */
    public int $requestedFromPage = 0;

    /**
     * @param  list<list<SourceReview>>  $pages
     * @param  Throwable|null  $failure  Бросается после `failAfterPages` страниц (0 — вместо карточки).
     */
    public function __construct(
        public SourceOrganization $organization = new SourceOrganization('Вольт 11', 'ул. Савина, 20', 4.5, 416, 191),
        public array $pages = [],
        public int $expectedTotal = 0,
        public ?Throwable $failure = null,
        public int $failAfterPages = 0,
    ) {}

    /**
     * @param  list<string>  $externalIds
     */
    public static function review(string $externalId, string $text = 'Хороший сервис', int $rating = 5, ?string $reply = null): SourceReview
    {
        return new SourceReview(
            externalId: $externalId,
            authorName: 'Автор '.$externalId,
            publishedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
            text: $text,
            rating: $rating,
            businessReply: $reply,
        );
    }

    public function fetchOrganization(SourceReference $source): SourceOrganization
    {
        $this->organizationCalls++;

        if ($this->failure !== null && $this->failAfterPages === 0) {
            throw $this->failure;
        }

        return $this->organization;
    }

    /**
     * Страницы нумеруются абсолютно, поэтому возобновлённый сбор пропускает уже сохранённое — ровно так же,
     * как настоящий адаптер просит у площадки страницу подальше.
     *
     * @return Generator<int, ReviewPage>
     */
    public function fetchReviews(SourceReference $source, int $fromPage = 1): Generator
    {
        $this->requestedFromPage = $fromPage;
        $page = 0;

        foreach ($this->pages as $reviews) {
            $page++;

            if ($page < $fromPage) {
                continue;
            }

            $this->deliveredPages = $page;

            yield new ReviewPage($reviews, $page, $this->expectedTotal);

            if ($this->failure !== null && $page >= $this->failAfterPages) {
                throw $this->failure;
            }
        }
    }
}
