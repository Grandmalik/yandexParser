<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Illuminate\Http\JsonResponse;

/**
 * Отдаёт параметры интерфейса и словари, которые SPA не должен зашивать у себя.
 */
final readonly class ShowAppConfigController
{
    public function __invoke(AppConfigAssembler $assembler): JsonResponse
    {
        return new JsonResponse(['data' => $assembler->assemble()]);
    }
}
