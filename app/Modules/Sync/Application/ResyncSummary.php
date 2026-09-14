<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

/**
 * Итог одного тика планового ресинка — в тех терминах, в которых о нём спрашивает эксплуатация.
 */
final readonly class ResyncSummary
{
    public function __construct(
        public int $due,
        public int $queued,
        public int $alreadyRunning,
        public int $platformPaused,
    ) {}
}
