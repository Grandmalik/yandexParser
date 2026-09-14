<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use RuntimeException;

/**
 * Организация исчезла между чтением и записью — например, её удалили, пока шёл сбор.
 */
final class OrganizationMissing extends RuntimeException {}
