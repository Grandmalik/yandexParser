<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use DateTimeImmutable;

/**
 * Хранилище организаций.
 */
interface OrganizationRepository
{
    /**
     * Сохраняет организацию, если карточки с тем же источником ещё нет (в том числе при одновременных
     * вызовах); в любом случае возвращает сохранённую.
     */
    public function saveIfAbsent(Organization $organization): Organization;

    /**
     * Записывает изменения организации.
     */
    public function save(Organization $organization): void;

    /**
     * Организация по id или null.
     */
    public function findById(OrganizationId $id): ?Organization;

    /**
     * Организации, чьи показатели читались раньше указанного момента; никогда не читанные — первыми.
     *
     * @return list<Organization>
     */
    public function listDueForResync(DateTimeImmutable $lastReadBefore, int $limit): array;

    /**
     * Даёт пользователю доступ к организации; повторная выдача ничего не меняет.
     */
    public function addMember(OrganizationId $id, int $userId): void;

    /**
     * Есть ли у пользователя доступ к организации.
     */
    public function isMember(OrganizationId $id, int $userId): bool;

    /**
     * Сохраняет показатели на момент времени, чтобы «было → стало» осталось видно между сборами.
     */
    public function addSnapshot(OrganizationId $id, ?string $syncRunId, RatingSummary $summary, DateTimeImmutable $capturedAt): void;

    /**
     * Снимки организации, старые сверху, не больше `$limit` последних.
     *
     * @return list<MetricsSnapshot>
     */
    public function snapshots(OrganizationId $id, int $limit): array;
}
