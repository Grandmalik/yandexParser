<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Domain\MetricsSnapshot;
use DateTimeImmutable;

/**
 * Снимок показателей вместе с разницей к предыдущему («было → стало»). Разницу считает бэкенд, клиенту
 * остаётся только показать числа.
 */
final readonly class MetricsSnapshotView
{
    public function __construct(
        public DateTimeImmutable $capturedAt,
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public ?float $ratingChange,
        public ?int $ratingsCountChange,
        public ?int $reviewsCountChange,
    ) {}

    /**
     * Считает разницу между снимком и предыдущим.
     *
     * @param  MetricsSnapshot|null  $previous  null для самого первого снимка: сравнивать не с чем.
     */
    public static function comparedTo(MetricsSnapshot $snapshot, ?MetricsSnapshot $previous): self
    {
        return new self(
            capturedAt: $snapshot->capturedAt,
            rating: $snapshot->summary->rating,
            ratingsCount: $snapshot->summary->ratingsCount,
            reviewsCount: $snapshot->summary->reviewsCount,
            ratingChange: $previous === null ? null : round($snapshot->summary->rating - $previous->summary->rating, 2),
            ratingsCountChange: $previous === null ? null : $snapshot->summary->ratingsCount - $previous->summary->ratingsCount,
            reviewsCountChange: $previous === null ? null : $snapshot->summary->reviewsCount - $previous->summary->reviewsCount,
        );
    }
}
