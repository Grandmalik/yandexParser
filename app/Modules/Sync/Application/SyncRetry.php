<?php

declare(strict_types=1);

namespace App\Modules\Sync\Application;

use App\Modules\Scraping\Application\Contracts\RetryPolicy;
use Throwable;

/**
 * Сколько ждать до следующей попытки сбора — или null, если повторять не нужно.
 */
final readonly class SyncRetry
{
    public function __construct(
        private RetryPolicy $policy,
        private int $maxAttempts,
    ) {}

    /**
     * Задержка до повтора; null — попытки кончились или ошибка повтором не чинится.
     */
    public function delayFor(Throwable $failure, int $attempt): ?int
    {
        return $attempt >= $this->maxAttempts ? null : $this->policy->delayAfter($failure, $attempt);
    }
}
