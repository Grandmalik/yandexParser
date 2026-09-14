<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

/**
 * Хранилище прогонов сбора.
 */
interface SyncRunRepository
{
    /**
     * Прогон по id или null.
     */
    public function find(SyncRunId $id): ?SyncRun;

    /**
     * Прогон этой организации, который стоит в очереди или выполняется, если такой есть.
     */
    public function activeFor(string $organizationId): ?SyncRun;

    /**
     * Последний по времени прогон организации.
     */
    public function latestFor(string $organizationId): ?SyncRun;

    /**
     * Сохраняет состояние прогона.
     */
    public function save(SyncRun $run): void;
}
