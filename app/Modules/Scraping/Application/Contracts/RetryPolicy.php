<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use Random\Randomizer;
use Throwable;

/**
 * Когда повторять работу, которая ходит на площадку: экспоненциальная задержка с джиттером (чтобы воркеры не
 * возвращались строем), дольше после бана, не раньше, чем попросила сама площадка, и никогда — для ошибок,
 * которые повтор не чинит.
 */
final readonly class RetryPolicy
{
    private const int MAX_SHIFT = 20;

    public function __construct(
        private int $baseSeconds,
        private int $maxSeconds,
        private int $blockedBaseSeconds,
        private Randomizer $random = new Randomizer,
    ) {}

    /**
     * Сколько секунд ждать после неудачной попытки `$attempt` (счёт с единицы); null — повтор не поможет.
     */
    public function delayAfter(Throwable $failure, int $attempt): ?int
    {
        return match (true) {
            $failure instanceof SourceDrift,
            $failure instanceof SourceNotFound,
            $failure instanceof InvalidSourceUrl => null,
            $failure instanceof SourceRateLimited => max($failure->retryAfterSeconds ?? 0, $this->backoff($attempt, $this->baseSeconds)),
            $failure instanceof SourceBlocked => max($failure->retryAfterSeconds ?? 0, $this->backoff($attempt, $this->blockedBaseSeconds)),
            default => $this->backoff($attempt, $this->baseSeconds),
        };
    }

    /**
     * «Равный джиттер»: не меньше половины экспоненциального шага, поэтому повтор никогда не мгновенный.
     */
    private function backoff(int $attempt, int $baseSeconds): int
    {
        $ceiling = min($this->maxSeconds, $baseSeconds << min(self::MAX_SHIFT, max(0, $attempt - 1)));

        return $this->random->getInt(intdiv($ceiling, 2), $ceiling);
    }
}
