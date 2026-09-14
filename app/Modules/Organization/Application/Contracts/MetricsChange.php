<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

/**
 * Какими показатели были до этого сбора и какими стали.
 */
final readonly class MetricsChange
{
    public function __construct(
        public ?RatingFigures $before,
        public RatingFigures $after,
    ) {}

    /**
     * Изменились ли показатели; первый сбор считается изменением — сравнивать не с чем.
     */
    public function changed(): bool
    {
        return $this->before === null || ! $this->before->equals($this->after);
    }
}
