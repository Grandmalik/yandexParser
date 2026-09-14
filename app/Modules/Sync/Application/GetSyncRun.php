<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncRunRepository;

/**
 * Прогон виден только участникам его организации; для остальных он просто не существует.
 */
final readonly class GetSyncRun
{
    public function __construct(
        private SyncRunRepository $runs,
        private OrganizationDirectory $organizations,
    ) {}

    /**
     * Прогон по id; null — нет такого или организация пользователю не видна.
     */
    public function byId(SyncRunId $id, int $userId): ?SyncRunView
    {
        $run = $this->runs->find($id);

        return $run !== null && $this->organizations->isMember($run->organizationId, $userId)
            ? SyncRunView::fromSyncRun($run)
            : null;
    }

    /**
     * Последний прогон организации — его состояние показывает экран.
     */
    public function latestForOrganization(string $organizationId, int $userId): ?SyncRunView
    {
        if (! $this->organizations->isMember($organizationId, $userId)) {
            return null;
        }

        $run = $this->runs->latestFor($organizationId);

        return $run === null ? null : SyncRunView::fromSyncRun($run);
    }
}
