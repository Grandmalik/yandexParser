<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Resilience;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Infrastructure\Http\ProxyPool;
use App\Modules\Scraping\Infrastructure\Http\ScrapeSession;
use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Реагирует на «нас забанили» при любом обращении к площадке: маршрут уходит в карантин, а повторные баны
 * ставят на паузу весь хост (circuit breaker) — чтобы воркеры не долбили площадку, которая нас уже блокирует,
 * и не продлевали этим бан.
 */
final readonly class BanProtection
{
    public function __construct(
        private CircuitBreaker $breaker,
        private ProxyPool $proxies,
        private int $banSeconds,
        private int $rateLimitCooldownSeconds,
    ) {}

    /**
     * Выполняет обращение к площадке под защитой: сперва проверяет паузу хоста, затем ловит бан и троттлинг,
     * а успех засчитывает в пользу хоста.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $call
     * @return TResult
     *
     * @throws SourceBlocked
     * @throws SourceRateLimited
     */
    public function call(string $host, ScrapeSession $session, Closure $call): mixed
    {
        $retryAfter = $this->breaker->retryAfter($host);

        if ($retryAfter !== null) {
            throw SourceBlocked::circuitOpen($retryAfter);
        }

        try {
            $result = $call();
        } catch (SourceBlocked $blocked) {
            $this->proxies->ban($session->proxy, $this->banSeconds);
            $this->breaker->recordFailure($host);

            Log::channel('scraping')->warning('Blocked by the platform: route quarantined.', [
                'host' => $host,
                'proxy' => $session->proxy->id,
                'ban_seconds' => $this->banSeconds,
            ]);

            throw $blocked;
        } catch (SourceRateLimited $limited) {
            $this->proxies->ban($session->proxy, max($limited->retryAfterSeconds ?? 0, $this->rateLimitCooldownSeconds));

            Log::channel('scraping')->warning('Throttled by the platform: route cooling down.', [
                'host' => $host,
                'proxy' => $session->proxy->id,
                'retry_after' => $limited->retryAfterSeconds,
            ]);

            throw $limited;
        }

        $this->breaker->recordSuccess($host);

        return $result;
    }
}
