<?php

declare(strict_types=1);

namespace App\Modules\Review\Infrastructure\Persistence;

use App\Modules\Review\Application\Contracts\ReviewCatalog;
use App\Modules\Review\Application\Contracts\ReviewView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final readonly class EloquentReviewCatalog implements ReviewCatalog
{
    public function page(string $organizationId, int $page, int $perPage): LengthAwarePaginator
    {
        return $this->stored()
            ->where('organization_id', $organizationId)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page)
            ->through($this->toView(...));
    }

    public function countFor(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        // Явный псевдоним: имя колонки агрегата без него у каждой СУБД своё (PostgreSQL — «count», SQLite — «count(*)»).
        $totals = $this->stored()
            ->select('organization_id')
            ->selectRaw('count(*) as total')
            ->whereIn('organization_id', $organizationIds)
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id');

        $counts = array_fill_keys($organizationIds, 0);

        foreach ($totals as $organizationId => $total) {
            $counts[(string) $organizationId] = is_numeric($total) ? (int) $total : 0;
        }

        return $counts;
    }

    /**
     * Базовый запрос: только отзывы, не помеченные удалёнными.
     *
     * @return Builder<ReviewModel>
     */
    private function stored(): Builder
    {
        return ReviewModel::query()->whereNull('removed_at');
    }

    /**
     * Строка БД → отзыв для показа.
     */
    private function toView(ReviewModel $review): ReviewView
    {
        return new ReviewView(
            id: $review->id,
            authorName: $review->author_name,
            publishedAt: $review->published_at->toDateTimeImmutable(),
            text: $review->text,
            rating: $review->rating,
            businessReply: $review->business_reply,
        );
    }
}
