<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use App\Modules\Shared\Domain\Identity\UuidIdentifier;

/**
 * Идентификатор организации.
 */
final readonly class OrganizationId extends UuidIdentifier {}
