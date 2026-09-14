<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Listeners;

use App\Modules\Organization\Application\Contracts\Events\OrganizationConnected;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Domain\SyncTrigger;

/**
 * У только что подключённой карточки данных ещё нет, поэтому сбор запускается сразу.
 */
final readonly class StartSyncOnOrganizationConnected
{
    public function __construct(private RequestSync $requestSync) {}

    /**
     * Ставит первый сбор для подключённой организации.
     */
    public function handle(OrganizationConnected $event): void
    {
        $this->requestSync->handle($event->organizationId, SyncTrigger::Connected);
    }
}
