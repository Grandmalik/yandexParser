<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Health;

use App\Modules\Scraping\Application\Health\HealthWindow;
use App\Modules\Shared\Domain\Source\Platform;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Держит окно исходов в том же кэше, который разделяют все воркеры, — рядом с остальным анти-бан-состоянием.
 */
final readonly class CachedHealthWindow implements HealthWindow
{
    private const string KEY = 'scraping:health:';

    public function __construct(
        private CacheRepository $state,
        private int $size,
        private int $ttlSeconds,
    ) {}

    public function record(Platform $platform, string $outcome): void
    {
        $outcomes = array_slice([$outcome, ...$this->recent($platform)], 0, $this->size);

        $this->state->put(self::KEY.$platform->value, $outcomes, $this->ttlSeconds);
    }

    public function recent(Platform $platform): array
    {
        $stored = $this->state->get(self::KEY.$platform->value);

        return is_array($stored) ? array_values(array_filter($stored, is_string(...))) : [];
    }
}
