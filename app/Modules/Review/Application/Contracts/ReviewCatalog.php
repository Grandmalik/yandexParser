<?php

declare(strict_types=1);

namespace App\Modules\Review\Application\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Чтение сохранённых отзывов. Собранное лежит в БД, поэтому страница отдаётся из неё, а не выкачивается
 * с площадки на каждый запрос (ADR-007).
 */
interface ReviewCatalog
{
    /**
     * Страница отзывов, новые сверху; отзывы, которых площадка больше не показывает, не попадают.
     *
     * @return LengthAwarePaginator<int, ReviewView>
     */
    public function page(string $organizationId, int $page, int $perPage): LengthAwarePaginator;

    /**
     * Сколько отзывов у нас сохранено — обычно меньше, чем показывает площадка: она ограничивает выдачу.
     *
     * @param  list<string>  $organizationIds
     * @return array<string, int> Количество по id организации, одним запросом.
     */
    public function countFor(array $organizationIds): array;
}
