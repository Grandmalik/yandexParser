<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\RatingSummary;
use App\Modules\Shared\Domain\Source\Platform;
use DateTimeImmutable;

/**
 * Организация в терминах приложения — то, что Application-слой отдаёт наружу.
 */
final readonly class OrganizationView
{
    /**
     * @param  int  $reviewsStored  Сколько отзывов собрано у нас; счётчик самой площадки лежит в $ratingSummary.
     */
    public function __construct(
        public string $id,
        public Platform $platform,
        public string $externalId,
        public string $sourceUrl,
        public ?string $name,
        public ?string $address,
        public ?RatingSummary $ratingSummary,
        public ?DateTimeImmutable $metricsUpdatedAt,
        public int $reviewsStored = 0,
    ) {}

    public static function fromOrganization(Organization $organization, int $reviewsStored = 0): self
    {
        return new self(
            reviewsStored: $reviewsStored,
            id: $organization->id->value,
            platform: $organization->source->platform,
            externalId: $organization->source->externalId,
            sourceUrl: $organization->sourceUrl,
            name: $organization->name(),
            address: $organization->address(),
            ratingSummary: $organization->ratingSummary(),
            metricsUpdatedAt: $organization->metricsUpdatedAt(),
        );
    }
}
