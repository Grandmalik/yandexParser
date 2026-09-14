<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Пользователь подключил карточку организации — новую или уже известную; её данные нужно собрать.
 */
final readonly class OrganizationConnected implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public int $userId,
    ) {}
}
