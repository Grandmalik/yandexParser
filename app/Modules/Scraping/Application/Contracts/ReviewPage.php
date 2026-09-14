<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

/**
 * Одна страница отзывов, полученная с площадки.
 */
final readonly class ReviewPage
{
    /**
     * @param  list<SourceReview>  $reviews  Отзывы, которых не было на предыдущих страницах этого же потока.
     * @param  int  $expectedTotal  Сколько отзывов площадка готова отдать: её счётчик, обрезанный пределом выдачи.
     */
    public function __construct(
        public array $reviews,
        public int $page,
        public int $expectedTotal,
    ) {}
}
