<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Resilience;

use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Shared\Domain\Source\Platform;

/**
 * Сообщает ту же паузу, которую circuit breaker уже держит для хоста площадки: вызывающий видит состояние,
 * которое реально соблюдает транспорт, а не второе мнение о нём.
 */
final readonly class BreakerSourceAvailability implements SourceAvailability
{
    /**
     * @param  array<string, string>  $hosts  Значение площадки → хост, по которому breaker ведёт учёт.
     */
    public function __construct(
        private CircuitBreaker $breaker,
        private array $hosts,
    ) {}

    public function pausedFor(Platform $platform): ?int
    {
        $host = $this->hosts[$platform->value] ?? null;

        return $host === null ? null : $this->breaker->retryAfter($host);
    }
}
