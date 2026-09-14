<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

use DateTimeImmutable;

/**
 * Как другие модули добираются до организаций: только по id и только через этот контракт — никаких
 * Eloquent-моделей чужого модуля.
 */
interface OrganizationDirectory
{
    /**
     * Организация по id или null.
     */
    public function find(string $organizationId): ?ConnectedOrganization;

    /**
     * Есть ли у пользователя доступ к организации.
     */
    public function isMember(string $organizationId, int $userId): bool;

    /**
     * Организации, чьи показатели читались раньше указанного момента; никогда не читанные — первыми.
     * Нужен плановому ресинку: он берёт ограниченную партию за тик, а не весь список разом.
     *
     * @return list<ConnectedOrganization>
     */
    public function dueForResync(DateTimeImmutable $lastReadBefore, int $limit): array;

    /**
     * Сохраняет карточку в том виде, в каком её показывает площадка, и снимок её показателей — чтобы
     * изменения остались видны. Если показатели разошлись с прошлым сбором, публикует
     * OrganizationMetricsChanged.
     */
    public function recordCard(string $organizationId, ?string $syncRunId, OrganizationCard $card, DateTimeImmutable $observedAt): MetricsChange;
}
