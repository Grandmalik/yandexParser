<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Сбор завершился, данные сохранены; `complete` = false, если отзывов собрано меньше ожидаемого.
 */
final readonly class SyncCompleted implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $syncRunId,
        public string $organizationId,
        public bool $complete,
        public int $collected,
        public int $expected,
    ) {}
}
