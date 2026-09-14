<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use App\Modules\Organization\Domain\MetricsSnapshot;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Organization\Domain\RatingSummary;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Psr\Clock\ClockInterface;

final readonly class EloquentOrganizationRepository implements OrganizationRepository
{
    private const string SNAPSHOTS_TABLE = 'organization_snapshots';

    public function __construct(private ClockInterface $clock) {}

    public function saveIfAbsent(Organization $organization): Organization
    {
        $record = OrganizationModel::query()->createOrFirst(
            ['platform' => $organization->source->platform->value, 'external_id' => $organization->source->externalId],
            ['id' => $organization->id->value, 'source_url' => $organization->sourceUrl],
        );

        return OrganizationRecordMapper::toDomain($record);
    }

    public function save(Organization $organization): void
    {
        $summary = $organization->ratingSummary();

        OrganizationModel::query()->whereKey($organization->id->value)->update([
            'name' => $organization->name(),
            'address' => $organization->address(),
            'rating' => $summary?->rating,
            'ratings_count' => $summary?->ratingsCount,
            'reviews_count' => $summary?->reviewsCount,
            'metrics_updated_at' => $organization->metricsUpdatedAt(),
        ]);
    }

    public function findById(OrganizationId $id): ?Organization
    {
        $record = OrganizationModel::query()->find($id->value);

        return $record === null ? null : OrganizationRecordMapper::toDomain($record);
    }

    public function listDueForResync(DateTimeImmutable $lastReadBefore, int $limit): array
    {
        return array_values(
            OrganizationModel::query()
                ->where(static fn (Builder $query): Builder => $query
                    ->whereNull('metrics_updated_at')
                    ->orWhere('metrics_updated_at', '<', $lastReadBefore))
                // Никогда не читанные — первыми; PostgreSQL и SQLite упорядочивают это сравнение одинаково.
                ->orderByDesc(DB::raw('metrics_updated_at is null'))
                ->orderBy('metrics_updated_at')
                ->limit($limit)
                ->get()
                ->map(OrganizationRecordMapper::toDomain(...))
                ->all()
        );
    }

    public function addMember(OrganizationId $id, int $userId): void
    {
        DB::table(OrganizationModel::MEMBERS_TABLE)->insertOrIgnore([
            'organization_id' => $id->value,
            'user_id' => $userId,
            'connected_at' => $this->clock->now(),
        ]);
    }

    public function isMember(OrganizationId $id, int $userId): bool
    {
        return DB::table(OrganizationModel::MEMBERS_TABLE)
            ->where('organization_id', $id->value)
            ->where('user_id', $userId)
            ->exists();
    }

    public function addSnapshot(OrganizationId $id, ?string $syncRunId, RatingSummary $summary, DateTimeImmutable $capturedAt): void
    {
        DB::table(self::SNAPSHOTS_TABLE)->insert([
            'organization_id' => $id->value,
            'sync_run_id' => $syncRunId,
            'rating' => $summary->rating,
            'ratings_count' => $summary->ratingsCount,
            'reviews_count' => $summary->reviewsCount,
            'captured_at' => $capturedAt,
        ]);
    }

    public function snapshots(OrganizationId $id, int $limit): array
    {
        return array_values(
            OrganizationSnapshotModel::query()
                ->where('organization_id', $id->value)
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->map(static fn (OrganizationSnapshotModel $snapshot): MetricsSnapshot => new MetricsSnapshot(
                    capturedAt: $snapshot->captured_at->toDateTimeImmutable(),
                    summary: new RatingSummary(
                        rating: (float) $snapshot->rating,
                        ratingsCount: $snapshot->ratings_count,
                        reviewsCount: $snapshot->reviews_count,
                    ),
                ))
                ->all()
        );
    }
}
