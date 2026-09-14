<?php

declare(strict_types=1);

namespace Tests\Support;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * Часы, которые идут только когда им скажут, — для unit-тестов без фреймворка.
 */
final class TestClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now = new DateTimeImmutable('2026-09-12 10:00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(int $milliseconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d milliseconds', $milliseconds));
    }
}
