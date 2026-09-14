<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts\Exceptions;

use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Sync\Application\Contracts\SyncErrorCode;

/**
 * Данные этой организации уже собираются: клиенту следует следить за идущим прогоном, а не запускать ещё один.
 */
final class SyncAlreadyRunning extends DomainException
{
    /**
     * Ошибка с id активного прогона в деталях — фронтенд подписывается именно на него.
     */
    public static function as(string $syncRunId): self
    {
        return new self(SyncErrorCode::AlreadyRunning, details: ['sync_run_id' => $syncRunId]);
    }
}
