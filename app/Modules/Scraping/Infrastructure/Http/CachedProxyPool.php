<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Psr\Clock\ClockInterface;

/**
 * Ротация по настроенным маршрутам; баны общие для всех воркеров, потому что живут в кэше.
 */
final readonly class CachedProxyPool implements ProxyPool
{
    private const string BANNED_UNTIL_KEY = 'scraping:proxy:banned_until:';

    private const string LAST_USED_KEY = 'scraping:proxy:last_used:';

    private const string SEQUENCE_KEY = 'scraping:proxy:sequence';

    /**
     * @param  non-empty-list<Proxy>  $proxies
     */
    public function __construct(
        private array $proxies,
        private CacheRepository $state,
        private ClockInterface $clock,
    ) {}

    public function acquire(): Proxy
    {
        $now = $this->clock->now()->getTimestamp();
        $available = array_values(array_filter($this->proxies, fn (Proxy $proxy): bool => $this->bannedUntil($proxy) <= $now));

        if ($available === []) {
            $soonest = min(array_map(fn (Proxy $proxy): int => $this->bannedUntil($proxy), $this->proxies));

            throw SourceBlocked::allProxiesBanned(max(1, $soonest - $now));
        }

        usort($available, fn (Proxy $a, Proxy $b): int => $this->lastUsed($a) <=> $this->lastUsed($b));
        $proxy = $available[0];

        // Общий счётчик вместо меток времени: несколько взятий внутри одного тика часов всё равно чередуются.
        $this->state->forever(self::LAST_USED_KEY.$proxy->id, $this->state->increment(self::SEQUENCE_KEY));

        return $proxy;
    }

    public function ban(Proxy $proxy, int $seconds): void
    {
        $until = $this->clock->now()->getTimestamp() + $seconds;

        if ($until > $this->bannedUntil($proxy)) {
            $this->state->put(self::BANNED_UNTIL_KEY.$proxy->id, $until, $seconds);
        }
    }

    /**
     * До какого момента маршрут в карантине; 0 — свободен.
     */
    private function bannedUntil(Proxy $proxy): int
    {
        $until = $this->state->get(self::BANNED_UNTIL_KEY.$proxy->id);

        return is_int($until) ? $until : 0;
    }

    /**
     * Порядковый номер последнего использования маршрута — по нему выбирается самый «отдохнувший».
     */
    private function lastUsed(Proxy $proxy): int
    {
        $sequence = $this->state->get(self::LAST_USED_KEY.$proxy->id);

        return is_int($sequence) ? $sequence : 0;
    }
}
