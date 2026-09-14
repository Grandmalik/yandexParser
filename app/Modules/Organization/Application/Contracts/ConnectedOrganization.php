<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application\Contracts;

use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Минимум, который нужен другим модулям для работы с организацией: её id и откуда её читать.
 */
final readonly class ConnectedOrganization
{
    public function __construct(
        public string $id,
        public SourceReference $source,
        public ?string $name,
    ) {}
}
