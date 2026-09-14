<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

/**
 * Карточка организации в том виде, в каком она сейчас на площадке.
 */
final readonly class OrganizationCard
{
    public function __construct(
        public string $name,
        public ?string $address,
        public RatingFigures $figures,
    ) {}
}
