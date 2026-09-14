<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Port;

use App\Modules\Sync\Domain\SyncRunId;

/**
 * Передаёт поставленный прогон воркеру. Сбор занимает минуты, поэтому внутри HTTP-запроса он не выполняется никогда.
 */
interface SyncRunner
{
    /**
     * Ставит прогон в очередь на выполнение.
     */
    public function enqueue(SyncRunId $id): void;
}
