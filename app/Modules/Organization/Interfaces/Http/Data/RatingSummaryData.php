<?php

declare(strict_types=1);

namespace App\Modules\Organization\Interfaces\Http\Data;

use App\Modules\Organization\Domain\RatingSummary;
use Spatie\LaravelData\Data;

/**
 * Показатели площадки в ответе API: средняя оценка и два счётчика.
 */
final class RatingSummaryData extends Data
{
    public function __construct(
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
    ) {}

    /**
     * Магический конструктор spatie/laravel-data: срабатывает на `RatingSummaryData::from($summary)`.
     */
    public static function fromRatingSummary(RatingSummary $summary): self
    {
        return new self($summary->rating, $summary->ratingsCount, $summary->reviewsCount);
    }
}
