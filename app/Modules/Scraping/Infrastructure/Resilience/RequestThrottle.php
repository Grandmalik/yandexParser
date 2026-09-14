<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Resilience;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Sleep;
use LogicException;
use Psr\Clock\ClockInterface;
use Random\Randomizer;

/**
 * Минимальный интервал плюс случайный джиттер между запросами с одним ключом — и это общее для всех воркеров:
 * следующий свободный слот лежит в кэше и занимается под атомарной блокировкой.
 */
final readonly class RequestThrottle
{
    private const string NEXT_SLOT_KEY = 'scraping:throttle:next:';

    private const string LOCK_KEY = 'scraping:throttle:lock:';

    private const int SLOT_TTL_MARGIN_SECONDS = 60;

    public function __construct(
        private CacheRepository $state,
        private ClockInterface $clock,
        private int $minIntervalMs,
        private int $jitterMs,
        private int $lockSeconds,
        private Randomizer $random = new Randomizer,
    ) {}

    /**
     * Дожидается своего слота и сразу резервирует следующий.
     */
    public function await(string $key): void
    {
        if ($this->minIntervalMs === 0 && $this->jitterMs === 0) {
            return;
        }

        $this->lock($key)->block($this->lockSeconds, function () use ($key): void {
            $now = $this->nowMs();
            $next = $this->state->get(self::NEXT_SLOT_KEY.$key);
            $slot = is_int($next) && $next > $now ? $next : $now;

            if ($slot > $now) {
                Sleep::for($slot - $now)->milliseconds();
            }

            $interval = $this->minIntervalMs + $this->random->getInt(0, $this->jitterMs);

            $this->state->put(
                self::NEXT_SLOT_KEY.$key,
                $slot + $interval,
                intdiv($interval, 1000) + self::SLOT_TTL_MARGIN_SECONDS,
            );
        });
    }

    /**
     * Блокировка из хранилища состояния; хранилище без атомарных блокировок — ошибка конфигурации.
     */
    private function lock(string $key): Lock
    {
        $store = $this->state->getStore();

        if (! $store instanceof LockProvider) {
            throw new LogicException('The scraping state store must support atomic locks (redis, database, array).');
        }

        return $store->lock(self::LOCK_KEY.$key, $this->lockSeconds);
    }

    private function nowMs(): int
    {
        return (int) $this->clock->now()->format('Uv');
    }
}
