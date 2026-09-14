<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Organization\Application\Contracts\OrganizationCard;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Organization\Application\Contracts\RatingFigures;
use App\Modules\Review\Application\Contracts\IncomingReview;
use App\Modules\Review\Application\Contracts\ReviewStore;
use App\Modules\Review\Application\Contracts\ReviewUpsertResult;
use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Scraping\Application\Contracts\SourceReview;
use App\Modules\Sync\Application\Contracts\Events\SyncCompleted;
use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Domain\CompletenessPolicy;
use App\Modules\Sync\Domain\SyncRun;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncRunRepository;
use App\Modules\Sync\Domain\SyncStats;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * Собирает данные организации: сначала показатели карточки, затем отзывы страница за страницей. Каждая страница
 * сохраняется до запроса следующей, поэтому падение в середине ничего не теряет, а повтор просто продолжает.
 */
final readonly class RunSync
{
    public function __construct(
        private SyncRunRepository $runs,
        private OrganizationDirectory $organizations,
        private ReviewStore $reviews,
        private ReviewSourceGateway $source,
        private CompletenessPolicy $completeness,
        private Dispatcher $events,
        private ClockInterface $clock,
    ) {}

    /**
     * Выполняет прогон целиком: карточка, все страницы отзывов, итоговый статус и статистика.
     */
    public function handle(SyncRunId $id): void
    {
        $run = $this->runs->find($id) ?? throw new RuntimeException("Sync run {$id->value} no longer exists.");

        if ($run->status()->isFinished()) {
            return;
        }

        $organization = $this->organizations->find($run->organizationId)
            ?? throw new RuntimeException("Organization {$run->organizationId} no longer exists.");

        $run->start($this->clock->now());
        $this->save($run);
        $startedAt = $run->startedAt() ?? $this->clock->now();

        $card = $this->source->fetchOrganization($organization->source);
        $this->organizations->recordCard($organization->id, $run->id->value, new OrganizationCard(
            name: $card->name,
            address: $card->address,
            figures: new RatingFigures($card->rating, $card->ratingsCount, $card->reviewsCount),
        ), $this->clock->now());

        $totals = new ReviewUpsertResult(0, 0, 0);
        // Возобновлённый прогон продолжает с того, что уже сохранили прошлые попытки, а не считает с нуля.
        $collected = $run->progressCurrent();
        $expected = $run->progressTotal() ?? 0;

        foreach ($this->source->fetchReviews($organization->source, $run->nextPage()) as $page) {
            $totals = $totals->plus($this->reviews->upsert(
                $organization->id,
                $run->id->value,
                $this->incoming($page),
                $this->clock->now(),
            ));

            $collected += count($page->reviews);
            $expected = $page->expectedTotal;

            $run->advance($collected, max($collected, $expected), $page->page + 1);
            $this->save($run);
        }

        $complete = $this->completeness->isComplete($collected, $expected);
        // Объявлять отзывы пропавшими вправе только полный сбор: после частичного «не видели» значит «не собрали».
        $removed = $complete ? $this->reviews->markMissingAsRemoved($organization->id, $startedAt, $this->clock->now()) : 0;
        $stats = new SyncStats($totals->created, $totals->updated, $totals->unchanged, $removed);

        $complete
            ? $run->complete($stats, $this->clock->now())
            : $run->completePartially($stats, ScrapingErrorCode::Partial, $this->clock->now());

        $this->save($run);
        $this->events->dispatch(new SyncCompleted($run->id->value, $organization->id, $complete, $collected, $expected));
    }

    /**
     * Сохраняет прогон и объявляет его новое состояние — чтобы прогресс было видно по ходу дела, а не в конце.
     */
    private function save(SyncRun $run): void
    {
        $this->runs->save($run);
        $this->events->dispatch(new SyncRunStateChanged(SyncRunView::fromSyncRun($run)));
    }

    /**
     * Страница отзывов площадки → отзывы в терминах хранилища.
     *
     * @return list<IncomingReview>
     */
    private function incoming(ReviewPage $page): array
    {
        return array_map(static fn (SourceReview $review): IncomingReview => new IncomingReview(
            externalId: $review->externalId,
            authorName: $review->authorName,
            publishedAt: $review->publishedAt,
            text: $review->text,
            rating: $review->rating,
            businessReply: $review->businessReply,
        ), $page->reviews);
    }
}
