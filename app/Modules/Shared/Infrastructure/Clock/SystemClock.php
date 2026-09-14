<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Clock;

use Carbon\CarbonImmutable;
use Psr\Clock\ClockInterface;

/**
 * Настоящее время; в тестах управляется тестовыми часами Carbon (`$this->travelTo()`).
 */
final class SystemClock implements ClockInterface
{
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }
}
