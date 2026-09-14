<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

use LogicException;

/**
 * Ошибка в коде: конвейер попытался перевести прогон в состояние, в которое из текущего попасть нельзя.
 */
final class InvalidSyncTransition extends LogicException
{
    /**
     * @param  list<SyncStatus>  $allowed
     */
    public static function from(SyncStatus $current, string $action, array $allowed): self
    {
        return new self(sprintf(
            'Cannot %s a sync run in status "%s"; allowed from: %s.',
            $action,
            $current->value,
            implode(', ', array_map(static fn (SyncStatus $status): string => $status->value, $allowed)),
        ));
    }
}
