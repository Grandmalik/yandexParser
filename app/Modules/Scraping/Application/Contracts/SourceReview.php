<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use DateTimeImmutable;

/**
 * Отзыв, прочитанный с площадки.
 */
final readonly class SourceReview
{
    /**
     * @param  string  $externalId  Идентификатор отзыва на площадке, не меняется между сборами.
     * @param  string|null  $authorName  null, если площадка не показывает автора (у старых отзывов).
     * @param  DateTimeImmutable  $publishedAt  В UTC; площадка отдаёт только время последнего изменения.
     * @param  int|null  $rating  1..5; null, если у отзыва нет оценки (у старых отзывов).
     */
    public function __construct(
        public string $externalId,
        public ?string $authorName,
        public DateTimeImmutable $publishedAt,
        public ?string $text,
        public ?int $rating,
        public ?string $businessReply,
    ) {}
}
