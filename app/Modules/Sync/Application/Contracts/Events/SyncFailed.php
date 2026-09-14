<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application\Contracts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Сбор закончился ошибкой.
 */
final readonly class SyncFailed implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $syncRunId,
        public string $organizationId,
        public string $errorCode,
    ) {}
}
