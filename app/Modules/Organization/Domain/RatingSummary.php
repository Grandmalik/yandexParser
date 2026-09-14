<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use InvalidArgumentException;

/**
 * Показатели в том виде, в каком их сообщает площадка. Мы никогда не пересчитываем их по сохранённым отзывам:
 * площадка учитывает и оценки без текста, и отзывы, которых нам не отдаёт.
 */
final readonly class RatingSummary
{
    public const float MAX_RATING = 5.0;

    public function __construct(
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
    ) {
        if ($rating < 0 || $rating > self::MAX_RATING) {
            throw new InvalidArgumentException(sprintf('Rating must be within 0..%s, %s given.', self::MAX_RATING, $rating));
        }

        if ($ratingsCount < 0 || $reviewsCount < 0) {
            throw new InvalidArgumentException('Ratings and reviews counts cannot be negative.');
        }
    }

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
