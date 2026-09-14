<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

use InvalidArgumentException;

/**
 * Что сбор сделал с сохранёнными отзывами: сколько создано, изменено, осталось прежними и помечено удалёнными.
 */
final readonly class SyncStats
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
        public int $removed,
    ) {
        if (min($created, $updated, $unchanged, $removed) < 0) {
            throw new InvalidArgumentException('Sync counters cannot be negative.');
        }
    }
}
