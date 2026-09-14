<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Чтение списка подключённых организаций. Страницу нарезает база, поэтому показ пятидесяти строк не тянет
 * весь список — так же, как это устроено для отзывов.
 */
interface OrganizationCatalog
{
    /**
     * Организации, к которым у пользователя есть доступ; недавно подключённые сверху.
     *
     * Возвращается сам пагинатор, а не простой список: вызывающий превращает его элементы в то, что видит
     * экран, и тип контракта обязан это позволять.
     *
     * @return LengthAwarePaginator<int, Organization>
     */
    public function pageForMember(int $userId, int $page, int $perPage): LengthAwarePaginator;
}
