<?php

declare(strict_types=1);

namespace App\Modules\Review\Interfaces\Http\Data;

use App\Modules\Review\Application\Contracts\ReviewView;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Отзыв в ответе API.
 */
final class ReviewData extends Data
{
    /**
     * @param  string|null  $authorName  null, если площадка скрывает автора.
     * @param  int|null  $rating  null, если у отзыва нет оценки.
     */
    public function __construct(
        public int $id,
        public ?string $authorName,
        public CarbonImmutable $publishedAt,
        public ?string $text,
        public ?int $rating,
        public ?string $businessReply,
    ) {}

    /**
     * Магический конструктор spatie/laravel-data: срабатывает на `ReviewData::from($view)`.
     */
    public static function fromReviewView(ReviewView $review): self
    {
        return new self(
            id: $review->id,
            authorName: $review->authorName,
            publishedAt: CarbonImmutable::instance($review->publishedAt),
            text: $review->text,
            rating: $review->rating,
            businessReply: $review->businessReply,
        );
    }
}
