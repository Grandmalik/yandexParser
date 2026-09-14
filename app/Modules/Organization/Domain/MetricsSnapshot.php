<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use DateTimeImmutable;

/**
 * Показатели организации на определённый момент времени.
 */
final readonly class MetricsSnapshot
{
    public function __construct(
        public DateTimeImmutable $capturedAt,
        public RatingSummary $summary,
    ) {}
}
