<?php

declare(strict_types=1);

namespace App\Modules\Scraping;

use App\Modules\Scraping\Application\Contracts\RetryPolicy;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Scraping\Application\Contracts\SourceUrlResolver;
use App\Modules\Scraping\Application\Health\HealthWindow;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Scraping\Application\HealthTrackingReviewSourceGateway;
use App\Modules\Scraping\Application\PlatformReviewSourceGateway;
use App\Modules\Scraping\Application\Port\ShortLinkExpander;
use App\Modules\Scraping\Application\SourceUrl\PlatformSourceUrlResolver;
use App\Modules\Scraping\Domain\Url\PlatformUrlParser;
use App\Modules\Scraping\Infrastructure\Health\CachedHealthWindow;
use App\Modules\Scraping\Infrastructure\Http\BrowserProfiles;
use App\Modules\Scraping\Infrastructure\Http\CachedProxyPool;
use App\Modules\Scraping\Infrastructure\Http\HttpShortLinkExpander;
use App\Modules\Scraping\Infrastructure\Http\Proxy;
use App\Modules\Scraping\Infrastructure\Http\ProxyPool;
use App\Modules\Scraping\Infrastructure\Http\ResponseGuard;
use App\Modules\Scraping\Infrastructure\Http\Transport\BanAwareTransport;
use App\Modules\Scraping\Infrastructure\Http\Transport\GuardedTransport;
use App\Modules\Scraping\Infrastructure\Http\Transport\LaravelTransport;
use App\Modules\Scraping\Infrastructure\Http\Transport\ThrottledTransport;
use App\Modules\Scraping\Infrastructure\Http\Transport\Transport;
use App\Modules\Scraping\Infrastructure\Resilience\BanProtection;
use App\Modules\Scraping\Infrastructure\Resilience\BreakerSourceAvailability;
use App\Modules\Scraping\Infrastructure\Resilience\CircuitBreaker;
use App\Modules\Scraping\Infrastructure\Resilience\RequestThrottle;
use App\Modules\Scraping\Infrastructure\Yandex\Signing\Djb2RequestSigner;
use App\Modules\Scraping\Infrastructure\Yandex\Signing\RequestSigner;
use App\Modules\Scraping\Infrastructure\Yandex\YandexSettings;
use App\Modules\Scraping\Interfaces\Console\CanaryCommand;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;
use UnexpectedValueException;

/**
 * Сервисы регистрируются как bind, а не как синглтоны: они дёшевы, читают конфигурацию в момент разрешения,
 * а всё анти-бан-состояние живёт в кэше, общем для воркеров, а не в объектах.
 */
final class ScrapingServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ShortLinkExpander::class => HttpShortLinkExpander::class,
        RequestSigner::class => Djb2RequestSigner::class,
    ];

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([CanaryCommand::class]);
        }
    }

    public function register(): void
    {
        $this->app->bind(YandexSettings::class, static fn (): YandexSettings => YandexSettings::fromConfig());

        $this->app->bind(SourceUrlResolver::class, fn (Application $app): SourceUrlResolver => new PlatformSourceUrlResolver(
            parsers: $this->platformUrlParsers(),
            shortLinks: $app->make(ShortLinkExpander::class),
            maxShortLinkHops: config()->integer('scraping.short_links.max_hops'),
        ));

        $this->app->bind(ReviewSourceGateway::class, fn (Application $app): ReviewSourceGateway => new PlatformReviewSourceGateway(
            $this->platformGateways($app),
        ));

        $this->app->bind(RetryPolicy::class, static fn (): RetryPolicy => new RetryPolicy(
            baseSeconds: config()->integer('scraping.retry.base_seconds'),
            maxSeconds: config()->integer('scraping.retry.max_seconds'),
            blockedBaseSeconds: config()->integer('scraping.retry.blocked_base_seconds'),
        ));

        $this->registerResilience();
        $this->registerTransport();
        $this->registerHealth();
    }

    private function registerHealth(): void
    {
        $this->app->bind(HealthWindow::class, fn (): HealthWindow => new CachedHealthWindow(
            state: $this->state(),
            size: config()->integer('scraping.health.window'),
            ttlSeconds: config()->integer('scraping.health.ttl_seconds'),
        ));

        $this->app->bind(SourceAvailability::class, fn (Application $app): SourceAvailability => new BreakerSourceAvailability(
            breaker: $app->make(CircuitBreaker::class),
            hosts: $this->platformHosts(),
        ));

        $this->app->bind(SourceHealth::class, static fn (Application $app): SourceHealth => new SourceHealth(
            window: $app->make(HealthWindow::class),
            availability: $app->make(SourceAvailability::class),
            logger: Log::channel('scraping'),
            degradedShare: config()->float('scraping.health.degraded_share'),
        ));
    }

    /**
     * Хост, по которому circuit breaker ведёт учёт площадки, — тот самый, куда уходят её запросы.
     *
     * @return array<string, string>
     */
    private function platformHosts(): array
    {
        $hosts = [];

        foreach (config()->array('scraping.platforms') as $platform => $settings) {
            $baseUrl = is_array($settings) && is_array($settings['http'] ?? null) ? $settings['http']['base_url'] ?? null : null;
            $host = is_string($baseUrl) ? parse_url($baseUrl, PHP_URL_HOST) : null;

            if (is_string($host)) {
                $hosts[(string) $platform] = $host;
            }
        }

        return $hosts;
    }

    private function registerResilience(): void
    {
        $this->app->bind(ProxyPool::class, fn (Application $app): ProxyPool => new CachedProxyPool(
            $this->proxies(),
            $this->state(),
            $app->make(ClockInterface::class),
        ));

        $this->app->bind(BrowserProfiles::class, static fn (): BrowserProfiles => BrowserProfiles::fromConfig(
            config()->array('scraping.browser_profiles'),
        ));

        $this->app->bind(RequestThrottle::class, fn (Application $app): RequestThrottle => new RequestThrottle(
            state: $this->state(),
            clock: $app->make(ClockInterface::class),
            minIntervalMs: config()->integer('scraping.throttle.min_interval_ms'),
            jitterMs: config()->integer('scraping.throttle.jitter_ms'),
            lockSeconds: config()->integer('scraping.throttle.lock_seconds'),
        ));

        $this->app->bind(CircuitBreaker::class, fn (Application $app): CircuitBreaker => new CircuitBreaker(
            state: $this->state(),
            clock: $app->make(ClockInterface::class),
            failureThreshold: config()->integer('scraping.circuit_breaker.failure_threshold'),
            openSeconds: config()->integer('scraping.circuit_breaker.open_seconds'),
        ));

        $this->app->bind(BanProtection::class, static fn (Application $app): BanProtection => new BanProtection(
            breaker: $app->make(CircuitBreaker::class),
            proxies: $app->make(ProxyPool::class),
            banSeconds: config()->integer('scraping.proxies.ban_seconds'),
            rateLimitCooldownSeconds: config()->integer('scraping.proxies.rate_limit_cooldown_seconds'),
        ));

        $this->app->bind(ResponseGuard::class, fn (): ResponseGuard => new ResponseGuard($this->stringList('scraping.blocked_markers')));
    }

    private function registerTransport(): void
    {
        $this->app->bind(Transport::class, static fn (Application $app): Transport => new BanAwareTransport(
            inner: new ThrottledTransport(
                inner: new GuardedTransport(
                    inner: new LaravelTransport(
                        $app->make(HttpFactory::class),
                        timeoutSeconds: config()->integer('scraping.http.timeout_seconds'),
                        connectTimeoutSeconds: config()->integer('scraping.http.connect_timeout_seconds'),
                    ),
                    guard: $app->make(ResponseGuard::class),
                ),
                throttle: $app->make(RequestThrottle::class),
            ),
            protection: $app->make(BanProtection::class),
        ));
    }

    /**
     * @return non-empty-list<Proxy>
     */
    private function proxies(): array
    {
        $proxies = array_map(Proxy::fromUrl(...), $this->stringList('scraping.proxies.urls'));

        return $proxies === [] ? [Proxy::direct()] : $proxies;
    }

    private function state(): CacheRepository
    {
        $store = config('scraping.state_store');

        return Cache::store(is_string($store) && $store !== '' ? $store : null);
    }

    /**
     * Адаптер каждой настроенной площадки, обёрнутый декоратором здоровья.
     *
     * @return array<string, ReviewSourceGateway>
     */
    private function platformGateways(Application $app): array
    {
        $gateways = [];

        foreach (config()->array('scraping.platforms') as $platform => $settings) {
            $settings = is_array($settings) ? $settings : [];
            $gateway = $this->gateway($app, $settings['gateway'] ?? null, "scraping.platforms.{$platform}.gateway");

            // Снаружи намеренно: здоровье считает то, что смог прочитать модуль в целом.
            $gateways[(string) $platform] = new HealthTrackingReviewSourceGateway($gateway, $app->make(SourceHealth::class));
        }

        return $gateways;
    }

    private function gateway(Application $app, mixed $class, string $key): ReviewSourceGateway
    {
        $gateway = is_string($class) ? $app->make($class) : null;

        return $gateway instanceof ReviewSourceGateway
            ? $gateway
            : throw new UnexpectedValueException("{$key} must name a ReviewSourceGateway.");
    }

    /**
     * По парсеру на каждую настроенную площадку, собранному из её собственного блока `url`. Добавить площадку —
     * это запись в конфиге плюс её адаптер: провайдер так и не узнаёт, какие площадки существуют.
     *
     * @return list<PlatformUrlParser>
     */
    private function platformUrlParsers(): array
    {
        $parsers = [];

        foreach (config()->array('scraping.platforms') as $platform => $settings) {
            $class = is_array($settings) ? $settings['url_parser'] ?? null : null;

            if (! is_string($class) || ! is_subclass_of($class, PlatformUrlParser::class)) {
                throw new UnexpectedValueException("scraping.platforms.{$platform}.url_parser must name a PlatformUrlParser.");
            }

            $parsers[] = $class::fromConfig(config()->array("scraping.platforms.{$platform}.url"));
        }

        return $parsers;
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        return array_values(array_filter(config()->array($key), is_string(...)));
    }
}
