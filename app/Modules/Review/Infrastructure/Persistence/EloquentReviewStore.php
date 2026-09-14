<?php

declare(strict_types=1);

namespace App\Modules\Review\Infrastructure\Persistence;

use App\Modules\Review\Application\Contracts\IncomingReview;
use App\Modules\Review\Application\Contracts\ReviewStore;
use App\Modules\Review\Application\Contracts\ReviewUpsertResult;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

/**
 * Отзыв уникален по паре «организация + внешний id», поэтому повторный сбор обновляет строки, а не плодит их.
 * Строка переписывается только при смене хеша содержимого, и каждая версия сохраняется как ревизия.
 */
final readonly class EloquentReviewStore implements ReviewStore
{
    private const string REVIEWS_TABLE = 'reviews';

    private const string REVISIONS_TABLE = 'review_revisions';

    /** Быстрый и короткий хеш: нужен только чтобы заметить изменение, не для безопасности. */
    private const string HASH_ALGORITHM = 'xxh128';

    public function __construct(private ConnectionInterface $connection) {}

    public function upsert(string $organizationId, ?string $syncRunId, array $reviews, DateTimeImmutable $observedAt): ReviewUpsertResult
    {
        if ($reviews === []) {
            return new ReviewUpsertResult(0, 0, 0);
        }

        $hashes = [];
        $incoming = [];

        foreach ($reviews as $review) {
            $hashes[$review->externalId] = $this->hash($review);
            $incoming[$review->externalId] = $review;
        }

        $stored = $this->connection->table(self::REVIEWS_TABLE)
            ->where('organization_id', $organizationId)
            ->whereIn('external_id', array_keys($hashes))
            ->pluck('content_hash', 'external_id');

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $changed = [];
        $rows = [];

        foreach ($incoming as $externalId => $review) {
            $previous = $stored[$externalId] ?? null;

            match (true) {
                $previous === null => [$created++, $changed[] = $externalId],
                $previous !== $hashes[$externalId] => [$updated++, $changed[] = $externalId],
                default => [$unchanged++],
            };

            $rows[] = [
                'organization_id' => $organizationId,
                'external_id' => $externalId,
                'author_name' => $review->authorName,
                'rating' => $review->rating,
                'text' => $review->text,
                'business_reply' => $review->businessReply,
                'published_at' => $review->publishedAt,
                'content_hash' => $hashes[$externalId],
                'first_seen_at' => $observedAt,
                'last_seen_at' => $observedAt,
                'created_at' => $observedAt,
                'updated_at' => $observedAt,
            ];
        }

        // first_seen_at и created_at не перечисляем: для знакомых отзывов это дата первой встречи, её не трогаем.
        $this->connection->table(self::REVIEWS_TABLE)->upsert($rows, ['organization_id', 'external_id'], [
            'author_name', 'rating', 'text', 'business_reply', 'published_at', 'content_hash', 'last_seen_at', 'updated_at',
        ]);

        $this->recordRevisions($organizationId, $syncRunId, $changed, $incoming, $hashes, $observedAt);

        return new ReviewUpsertResult($created, $updated, $unchanged);
    }

    public function markMissingAsRemoved(string $organizationId, DateTimeImmutable $notSeenSince, DateTimeImmutable $removedAt): int
    {
        return $this->connection->table(self::REVIEWS_TABLE)
            ->where('organization_id', $organizationId)
            ->whereNull('removed_at')
            ->where('last_seen_at', '<', $notSeenSince)
            ->update(['removed_at' => $removedAt, 'updated_at' => $removedAt]);
    }

    /**
     * Пишет ревизию для каждого отзыва, чьё содержимое изменилось.
     *
     * @param  list<string>  $changed
     * @param  array<string, IncomingReview>  $incoming
     * @param  array<string, string>  $hashes
     */
    private function recordRevisions(
        string $organizationId,
        ?string $syncRunId,
        array $changed,
        array $incoming,
        array $hashes,
        DateTimeImmutable $observedAt,
    ): void {
        if ($changed === []) {
            return;
        }

        $ids = $this->connection->table(self::REVIEWS_TABLE)
            ->where('organization_id', $organizationId)
            ->whereIn('external_id', $changed)
            ->pluck('id', 'external_id');

        $revisions = [];

        foreach ($changed as $externalId) {
            $review = $incoming[$externalId];

            $revisions[] = [
                'review_id' => $ids[$externalId],
                'sync_run_id' => $syncRunId,
                'rating' => $review->rating,
                'text' => $review->text,
                'business_reply' => $review->businessReply,
                'content_hash' => $hashes[$externalId],
                'captured_at' => $observedAt,
            ];
        }

        $this->connection->table(self::REVISIONS_TABLE)->insert($revisions);
    }

    /**
     * Хеш содержимого отзыва: оценка, текст и ответ организации.
     */
    private function hash(IncomingReview $review): string
    {
        return hash(self::HASH_ALGORITHM, implode("\0", [
            (string) $review->rating,
            (string) $review->text,
            (string) $review->businessReply,
        ]));
    }
}
