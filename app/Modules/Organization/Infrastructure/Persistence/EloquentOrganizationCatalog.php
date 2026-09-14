<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use App\Modules\Organization\Application\Contracts\OrganizationCatalog;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Список организаций пользователя постранично.
 */
final readonly class EloquentOrganizationCatalog implements OrganizationCatalog
{
    public function pageForMember(int $userId, int $page, int $perPage): LengthAwarePaginator
    {
        return OrganizationModel::ofMember($userId)
            // Сортировка по id разрешает ничьи: организации, подключённые в одну секунду, не должны меняться
            // местами между запросами — иначе одна строка попадёт на две страницы, а другая не попадёт никуда.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(perPage: $perPage, page: $page)
            ->through(OrganizationRecordMapper::toDomain(...));
    }
}
