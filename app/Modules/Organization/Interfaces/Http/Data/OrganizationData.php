<?php

declare(strict_types=1);

namespace App\Modules\Organization\Interfaces\Http\Data;

use App\Modules\Organization\Application\Contracts\OrganizationChannel;
use App\Modules\Organization\Application\OrganizationView;
use App\Modules\Shared\Interfaces\Http\Data\EnumOptionData;
use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class OrganizationData extends Data
{
    /**
     * @param  RatingSummaryData|null  $ratingSummary  Показатели площадки; null до первого успешного сбора.
     * @param  int  $reviewsStored  Сколько отзывов собрано: площадки ограничивают выдачу, поэтому это число
     *                              обычно меньше их собственного счётчика.
     */
    public function __construct(
        public string $id,
        public EnumOptionData $platform,
        public string $externalId,
        public string $sourceUrl,
        public ?string $name,
        public ?string $address,
        public ?RatingSummaryData $ratingSummary,
        public ?CarbonImmutable $metricsUpdatedAt,
        public int $reviewsStored,
        /** Куда приходят обновления по этой организации; клиент никогда не собирает имя канала сам. */
        public string $channel,
    ) {}

    /**
     * Магический конструктор spatie/laravel-data: срабатывает на `OrganizationData::from($view)`.
     */
    public static function fromOrganizationView(OrganizationView $organization): self
    {
        return new self(
            id: $organization->id,
            platform: EnumOptionData::fromEnum($organization->platform),
            externalId: $organization->externalId,
            sourceUrl: $organization->sourceUrl,
            name: $organization->name,
            address: $organization->address,
            ratingSummary: $organization->ratingSummary === null
                ? null
                : RatingSummaryData::fromRatingSummary($organization->ratingSummary),
            metricsUpdatedAt: $organization->metricsUpdatedAt === null
                ? null
                : CarbonImmutable::instance($organization->metricsUpdatedAt),
            reviewsStored: $organization->reviewsStored,
            channel: OrganizationChannel::for($organization->id),
        );
    }
}
