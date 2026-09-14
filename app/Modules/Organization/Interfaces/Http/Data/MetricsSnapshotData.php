<?php

declare(strict_types=1);

namespace App\Modules\Organization\Interfaces\Http\Data;

use App\Modules\Organization\Application\MetricsSnapshotView;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Строка истории показателей в ответе API.
 */
final class MetricsSnapshotData extends Data
{
    /**
     * @param  float|null  $ratingChange  Разница с предыдущим снимком; null у самого первого.
     */
    public function __construct(
        public CarbonImmutable $capturedAt,
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
        public ?float $ratingChange,
        public ?int $ratingsCountChange,
        public ?int $reviewsCountChange,
    ) {}

    /**
     * Магический конструктор spatie/laravel-data: срабатывает на `MetricsSnapshotData::from($view)`.
     */
    public static function fromMetricsSnapshotView(MetricsSnapshotView $snapshot): self
    {
        return new self(
            capturedAt: CarbonImmutable::instance($snapshot->capturedAt),
            rating: $snapshot->rating,
            ratingsCount: $snapshot->ratingsCount,
            reviewsCount: $snapshot->reviewsCount,
            ratingChange: $snapshot->ratingChange,
            ratingsCountChange: $snapshot->ratingsCountChange,
            reviewsCountChange: $snapshot->reviewsCountChange,
        );
    }
}
