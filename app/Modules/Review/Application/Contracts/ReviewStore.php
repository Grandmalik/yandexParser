<?php

declare(strict_types=1);

namespace App\Modules\Review\Application\Contracts;

use DateTimeImmutable;

/**
 * Запись отзывов.
 */
interface ReviewStore
{
    /**
     * Идемпотентно сохраняет пачку отзывов: знакомый отзыв обновляется на месте, а изменившееся содержимое
     * остаётся отдельной ревизией. Повторный сбор тех же данных дублей не создаёт.
     *
     * @param  list<IncomingReview>  $reviews
     */
    public function upsert(string $organizationId, ?string $syncRunId, array $reviews, DateTimeImmutable $observedAt): ReviewUpsertResult;

    /**
     * Помечает удалёнными отзывы, не встреченные с указанного момента, и возвращает их число. Вызывать можно
     * только после полного сбора: после частичного «не видели» означало бы «не собрали».
     */
    public function markMissingAsRemoved(string $organizationId, DateTimeImmutable $notSeenSince, DateTimeImmutable $removedAt): int;
}
