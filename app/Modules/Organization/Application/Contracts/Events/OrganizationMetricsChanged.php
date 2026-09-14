<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts\Events;

use App\Modules\Organization\Application\Contracts\RatingFigures;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Площадка сообщает показатели, отличные от тех, что были на прошлом сборе.
 */
final readonly class OrganizationMetricsChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $organizationId,
        public ?RatingFigures $before,
        public RatingFigures $after,
    ) {}
}
