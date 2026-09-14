<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Interfaces\Http\Data;

use App\Modules\Scraping\Application\Health\PlatformHealth;
use App\Modules\Shared\Interfaces\Http\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * Здоровье одной площадки в ответе `/api/v1/health`.
 */
final class PlatformHealthData extends Data
{
    public function __construct(
        public EnumOptionData $platform,
        public EnumOptionData $status,
        public int $checks,
        public int $failures,
        public float $failureShare,
        public ?string $lastError,
        public ?int $pausedForSeconds,
    ) {}

    /**
     * Состояние площадки → DTO ответа.
     */
    public static function fromPlatformHealth(PlatformHealth $health): self
    {
        return new self(
            platform: EnumOptionData::fromEnum($health->platform),
            status: EnumOptionData::fromEnum($health->status),
            checks: $health->checks,
            failures: $health->failures,
            failureShare: $health->failureShare,
            lastError: $health->lastError,
            pausedForSeconds: $health->pausedForSeconds,
        );
    }
}
