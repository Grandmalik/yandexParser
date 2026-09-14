<?php

declare(strict_types=1);

namespace App\Modules\Review\Application\Contracts;

use DateTimeImmutable;

/**
 * Отзыв, прочитанный с площадки и готовый к сохранению.
 */
final readonly class IncomingReview
{
    /**
     * @param  string  $externalId  Идентификатор отзыва на площадке; не меняется между сборами.
     * @param  string|null  $authorName  null, если площадка скрывает автора.
     * @param  int|null  $rating  null, если у отзыва нет оценки.
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
