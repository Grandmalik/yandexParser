<?php

declare(strict_types=1);

namespace App\Modules\Review\Application\Contracts;

use DateTimeImmutable;

/**
 * Сохранённый отзыв в том виде, в каком его видит пользователь.
 */
final readonly class ReviewView
{
    public function __construct(
        public int $id,
        public ?string $authorName,
        public DateTimeImmutable $publishedAt,
        public ?string $text,
        public ?int $rating,
        public ?string $businessReply,
    ) {}
}
