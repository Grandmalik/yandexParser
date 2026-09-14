<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

/**
 * Канал вещания одной организации: сюда уходит всё, что с ней происходит. Другие модули узнают имя канала
 * через этот контракт, а не собирают строку сами.
 */
final class OrganizationChannel
{
    private const string PREFIX = 'organizations.';

    /**
     * Форма с подстановкой — для `routes/channels.php`.
     */
    public const string PATTERN = self::PREFIX.'{organization}';

    /**
     * Имя канала конкретной организации.
     */
    public static function for(string $organizationId): string
    {
        return self::PREFIX.$organizationId;
    }
}
