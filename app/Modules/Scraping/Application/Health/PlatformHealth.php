<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Health;

use App\Modules\Shared\Domain\Source\Platform;

/**
 * Как площадка отвечала нам в последнее время.
 */
final readonly class PlatformHealth
{
    /**
     * @param  int  $checks  Сколько чтений в окне, по которому вынесен вердикт.
     * @param  float  $failureShare  Доля сбоев среди них, 0..1.
     * @param  string|null  $lastError  Код самой свежей ошибки, если она была.
     * @param  int|null  $pausedForSeconds  Через сколько секунд к площадке снова можно обращаться, если она на паузе.
     */
    public function __construct(
        public Platform $platform,
        public SourceStatus $status,
        public int $checks,
        public int $failures,
        public float $failureShare,
        public ?string $lastError,
        public ?int $pausedForSeconds,
    ) {}
}
