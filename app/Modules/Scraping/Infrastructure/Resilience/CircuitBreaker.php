<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Resilience;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Clock\ClockInterface;

/**
 * После `failureThreshold` подряд идущих сигналов бана от хоста обращения к нему отклоняются на `openSeconds`.
 * Когда это время выходит, цепь полуоткрыта: следующий запрос — пробный. Успех замыкает цепь, новый бан тут же
 * открывает её снова (счётчик неудач всё ещё на пороге).
 */
final readonly class CircuitBreaker
{
    private const string FAILURES_KEY = 'scraping:breaker:failures:';

    private const string OPEN_UNTIL_KEY = 'scraping:breaker:open_until:';

    public function __construct(
        private CacheRepository $state,
        private ClockInterface $clock,
        private int $failureThreshold,
        private int $openSeconds,
    ) {}

    /**
     * Через сколько секунд к хосту снова можно обращаться; null — можно сейчас (цепь замкнута или полуоткрыта).
     */
    public function retryAfter(string $key): ?int
    {
        $openUntil = $this->state->get(self::OPEN_UNTIL_KEY.$key);
        $left = is_int($openUntil) ? $openUntil - $this->now() : 0;

        return $left > 0 ? $left : null;
    }

    /**
     * Отмечает сигнал бана; на пороге неудач размыкает цепь.
     */
    public function recordFailure(string $key): void
    {
        $failures = $this->state->increment(self::FAILURES_KEY.$key);

        if (is_int($failures) && $failures >= $this->failureThreshold) {
            $this->state->put(self::OPEN_UNTIL_KEY.$key, $this->now() + $this->openSeconds, $this->openSeconds);
        }
    }

    /**
     * Успешное обращение замыкает цепь и обнуляет счётчик неудач.
     */
    public function recordSuccess(string $key): void
    {
        $this->state->forget(self::FAILURES_KEY.$key);
        $this->state->forget(self::OPEN_UNTIL_KEY.$key);
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
