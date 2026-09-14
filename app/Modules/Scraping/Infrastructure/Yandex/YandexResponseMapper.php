<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Scraping\Application\Contracts\SourceReview;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Переводит проверенные ответы Яндекса в DTO, не зависящие от площадки; любое нарушение схемы — это SourceDrift.
 */
final class YandexResponseMapper
{
    private const float MAX_RATING = 5.0;

    /** Старые отзывы (2017 года и раньше) приходят без оценки и без автора. */
    private const int NO_RATING = 0;

    private const int MIN_REVIEW_RATING = 1;

    private const int MAX_REVIEW_RATING = 5;

    /** Рейтинг приходит как float32 (4.900000095367432); сама площадка показывает его с одним знаком. */
    private const int RATING_PRECISION = 2;

    private const string ISO_DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/';

    /**
     * Показатели карточки: средняя оценка и два счётчика, с проверкой диапазонов.
     *
     * @throws SourceDrift
     */
    public function organization(PayloadReader $item): SourceOrganization
    {
        $ratingData = $item->object('ratingData');
        $rating = $ratingData->number('ratingValue');
        $ratingsCount = $ratingData->int('ratingCount');
        $reviewsCount = $ratingData->int('reviewCount');

        if ($rating < 0 || $rating > self::MAX_RATING) {
            throw $ratingData->invalid('ratingValue', 'within 0..'.self::MAX_RATING);
        }

        if ($ratingsCount < 0 || $reviewsCount < 0) {
            throw $ratingData->invalid('ratingCount', 'non-negative, as well as reviewCount');
        }

        return new SourceOrganization(
            name: $item->string('title'),
            address: $item->optionalString('address'),
            rating: round($rating, self::RATING_PRECISION),
            ratingsCount: $ratingsCount,
            reviewsCount: $reviewsCount,
        );
    }

    /**
     * Все отзывы одной страницы.
     *
     * @return list<SourceReview>
     *
     * @throws SourceDrift
     */
    public function reviews(PayloadReader $data): array
    {
        return array_map($this->review(...), $data->objects('reviews'));
    }

    /**
     * Один отзыв. Оценка 0 — это «без оценки», а не ошибка; всё, что вне 0 и 1..5, — смена формата.
     */
    private function review(PayloadReader $review): SourceReview
    {
        $rating = $review->int('rating');

        if ($rating !== self::NO_RATING && ($rating < self::MIN_REVIEW_RATING || $rating > self::MAX_REVIEW_RATING)) {
            throw $review->invalid(
                'rating',
                self::NO_RATING.' (no rating) or within '.self::MIN_REVIEW_RATING.'..'.self::MAX_REVIEW_RATING,
            );
        }

        $externalId = $review->string('reviewId');

        if ($externalId === '') {
            throw $review->invalid('reviewId', 'non-empty');
        }

        return new SourceReview(
            externalId: $externalId,
            authorName: $review->optionalObject('author')?->string('name'),
            publishedAt: $this->date($review, 'updatedTime'),
            text: $this->nonEmpty($review->string('text')),
            rating: $rating === self::NO_RATING ? null : $rating,
            businessReply: $this->nonEmpty($review->optionalObject('businessComment')?->optionalString('text')),
        );
    }

    /**
     * Дата отзыва в UTC; площадка отдаёт только время последнего изменения.
     */
    private function date(PayloadReader $review, string $key): DateTimeImmutable
    {
        $value = $review->string($key);

        if (preg_match(self::ISO_DATE_PATTERN, $value) !== 1) {
            throw $review->invalid($key, 'an ISO-8601 date-time');
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'));
        } catch (Exception) {
            throw $review->invalid($key, 'an ISO-8601 date-time');
        }
    }

    /**
     * Пустой текст приводится к null: «пусто» и «нет» не должны различаться на экране.
     */
    private function nonEmpty(?string $text): ?string
    {
        $text = $text === null ? null : trim($text);

        return $text === '' ? null : $text;
    }
}
