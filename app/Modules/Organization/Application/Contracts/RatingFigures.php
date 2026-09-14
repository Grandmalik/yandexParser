<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

/**
 * Показатели рейтинга, как их сообщает площадка.
 */
final readonly class RatingFigures
{
    public function __construct(
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
    ) {}

    /**
     * Совпадают ли все три числа.
     */
    public function equals(self $other): bool
    {
        return $other->rating === $this->rating
            && $other->ratingsCount === $this->ratingsCount
            && $other->reviewsCount === $this->reviewsCount;
    }
}
