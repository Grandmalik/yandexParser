<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

/**
 * Данные карточки организации ровно в том виде, в каком их показывает площадка.
 */
final readonly class SourceOrganization
{
    public function __construct(
        public string $name,
        public ?string $address,
        public float $rating,
        public int $ratingsCount,
        public int $reviewsCount,
    ) {}
}
