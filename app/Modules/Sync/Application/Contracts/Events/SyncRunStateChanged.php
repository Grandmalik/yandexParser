<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts\Events;

use App\Modules\Sync\Application\SyncRunView;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Состояние прогона сдвинулось: он начался, сохранилась очередная страница или он дошёл до конечного статуса.
 * Несёт состояние целиком, а не разницу, — чтобы слушателю не пришлось восстанавливать, что показать клиенту.
 */
final readonly class SyncRunStateChanged implements ShouldDispatchAfterCommit
{
    public function __construct(public SyncRunView $run) {}
}
