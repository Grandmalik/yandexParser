<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Health;

use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Source\Platform;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Превращает последние исходы чтения площадки в вердикт, по которому можно действовать. Смена формата —
 * деградация сама по себе: один drift означает, что парсер уже сломан, и ждать накопления доли сбоев незачем.
 */
final readonly class SourceHealth
{
    public const string OK = 'ok';

    public function __construct(
        private HealthWindow $window,
        private SourceAvailability $availability,
        private LoggerInterface $logger,
        private float $degradedShare,
    ) {}

    /**
     * Записывает удачное чтение площадки.
     */
    public function recordSuccess(Platform $platform): void
    {
        $this->record($platform, self::OK);
    }

    /**
     * Записывает сбой; у бизнес-ошибок сохраняется их код, у прочих — «internal».
     */
    public function recordFailure(Platform $platform, Throwable $failure): void
    {
        $this->record($platform, $failure instanceof DomainException ? $failure->errorCode->code() : 'internal');
    }

    /**
     * Состояние всех настроенных площадок.
     *
     * @return list<PlatformHealth>
     */
    public function report(): array
    {
        return array_map($this->platformHealth(...), Platform::cases());
    }

    /**
     * Вердикт по одной площадке.
     */
    public function statusOf(Platform $platform): SourceStatus
    {
        return $this->platformHealth($platform)->status;
    }

    /**
     * Худший вердикт среди площадок: один сломанный адаптер делает недоверенным весь слой источников.
     */
    public function overall(): SourceStatus
    {
        $statuses = array_map(static fn (PlatformHealth $health): SourceStatus => $health->status, $this->report());

        return match (true) {
            in_array(SourceStatus::Degraded, $statuses, true) => SourceStatus::Degraded,
            in_array(SourceStatus::Paused, $statuses, true) => SourceStatus::Paused,
            default => SourceStatus::Healthy,
        };
    }

    /**
     * Записывает исход и кричит в лог ровно в момент перехода в degraded, а не на каждом сбое подряд.
     */
    private function record(Platform $platform, string $outcome): void
    {
        $before = $this->platformHealth($platform)->status;
        $this->window->record($platform, $outcome);
        $after = $this->platformHealth($platform)->status;

        if ($after === SourceStatus::Degraded && $before !== SourceStatus::Degraded) {
            $this->logger->critical('Source health degraded: reading the platform keeps failing.', [
                'platform' => $platform->value,
                'last_outcome' => $outcome,
            ]);
        }
    }

    /**
     * Считает вердикт и счётчики по окну последних исходов.
     */
    private function platformHealth(Platform $platform): PlatformHealth
    {
        $outcomes = $this->window->recent($platform);
        $failures = array_values(array_filter($outcomes, static fn (string $o): bool => $o !== self::OK));
        $checks = count($outcomes);
        $share = $checks === 0 ? 0.0 : count($failures) / $checks;
        $pausedFor = $this->availability->pausedFor($platform);

        return new PlatformHealth(
            platform: $platform,
            status: $this->status($outcomes, $share, $pausedFor),
            checks: $checks,
            failures: count($failures),
            failureShare: round($share, 2),
            lastError: $failures[0] ?? null,
            pausedForSeconds: $pausedFor,
        );
    }

    /**
     * Правила вердикта: drift — сразу degraded; пауза circuit breaker'а — paused; доля сбоев выше порога — degraded.
     *
     * @param  list<string>  $outcomes
     */
    private function status(array $outcomes, float $failureShare, ?int $pausedFor): SourceStatus
    {
        return match (true) {
            in_array(ScrapingErrorCode::Drift->value, $outcomes, true) => SourceStatus::Degraded,
            $pausedFor !== null => SourceStatus::Paused,
            $outcomes !== [] && $failureShare >= $this->degradedShare => SourceStatus::Degraded,
            default => SourceStatus::Healthy,
        };
    }
}
