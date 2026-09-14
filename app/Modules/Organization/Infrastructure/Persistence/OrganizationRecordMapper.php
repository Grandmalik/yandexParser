<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\RatingSummary;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Единственное место, где запись из БД превращается в агрегат Organization: репозиторий читает и пишет
 * organizations по одной, каталог — страницами, и восстанавливать их они обязаны одинаково.
 */
final readonly class OrganizationRecordMapper
{
    /**
     * Строка таблицы `organizations` → доменный агрегат.
     */
    public static function toDomain(OrganizationModel $record): Organization
    {
        return Organization::restore(
            id: OrganizationId::fromString($record->id),
            source: new SourceReference(Platform::from($record->platform), $record->external_id),
            sourceUrl: $record->source_url,
            name: $record->name,
            address: $record->address,
            ratingSummary: $record->rating === null ? null : new RatingSummary(
                rating: (float) $record->rating,
                ratingsCount: (int) $record->ratings_count,
                reviewsCount: (int) $record->reviews_count,
            ),
            metricsUpdatedAt: $record->metrics_updated_at,
        );
    }
}
