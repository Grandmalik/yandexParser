<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Application\Contracts\OrganizationCatalog;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Review\Application\Contracts\ReviewCatalog;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Чтение организаций. Всё ограничено членством: организация, к которой у пользователя нет доступа, считается
 * несуществующей, а не запрещённой — мы не подтверждаем, что она вообще есть.
 */
final readonly class OrganizationQueries
{
    public function __construct(
        private OrganizationRepository $organizations,
        private OrganizationCatalog $catalog,
        private ReviewCatalog $reviews,
    ) {}

    /**
     * Одна страница организаций пользователя. Счётчики собранных отзывов читаются только для строк этой
     * страницы и одним запросом — экран не платит за остальной список.
     *
     * @return LengthAwarePaginator<int, OrganizationView>
     */
    public function forMember(int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        $organizations = $this->catalog->pageForMember($userId, $page, $perPage);
        $stored = $this->reviews->countFor(
            array_values(array_map(static fn (Organization $o): string => $o->id->value, $organizations->items())),
        );

        return $organizations->through(
            static fn (Organization $o): OrganizationView => OrganizationView::fromOrganization($o, $stored[$o->id->value] ?? 0),
        );
    }

    public function find(string $organizationId, int $userId): ?OrganizationView
    {
        $id = OrganizationId::fromString($organizationId);

        if (! $this->organizations->isMember($id, $userId)) {
            return null;
        }

        $organization = $this->organizations->findById($id);

        return $organization === null
            ? null
            : OrganizationView::fromOrganization($organization, $this->reviews->countFor([$organizationId])[$organizationId] ?? 0);
    }

    /**
     * История показателей, новые сверху; null — если пользователю эта организация не видна.
     *
     * @return list<MetricsSnapshotView>|null
     */
    public function history(string $organizationId, int $userId, int $limit): ?array
    {
        $id = OrganizationId::fromString($organizationId);

        if (! $this->organizations->isMember($id, $userId)) {
            return null;
        }

        $snapshots = $this->organizations->snapshots($id, $limit);
        $history = [];

        foreach ($snapshots as $index => $snapshot) {
            $history[] = MetricsSnapshotView::comparedTo($snapshot, $snapshots[$index - 1] ?? null);
        }

        return array_reverse($history);
    }
}
