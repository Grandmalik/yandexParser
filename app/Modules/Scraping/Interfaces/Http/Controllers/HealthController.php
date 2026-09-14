<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Interfaces\Http\Controllers;

use App\Modules\Scraping\Application\Health\PlatformHealth;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Scraping\Application\Health\SourceStatus;
use App\Modules\Scraping\Interfaces\Http\Data\PlatformHealthData;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Может ли приложение всё ещё читать площадки. Публичный и намеренно минимальный: он для аптайм-мониторинга,
 * поэтому отдаёт вердикты и счётчики — но никогда не то, что именно и для кого было прочитано.
 */
final readonly class HealthController
{
    public function __invoke(SourceHealth $health): JsonResponse
    {
        $overall = $health->overall();

        return new JsonResponse(
            [
                'data' => [
                    'status' => $overall->value,
                    'platforms' => array_map(
                        static fn (PlatformHealth $platform): array => PlatformHealthData::fromPlatformHealth($platform)->toArray(),
                        $health->report(),
                    ),
                ],
            ],
            // Деградировавший слой источников обязан провалить проверку монитора, а не упомянуть об этом в теле ответа.
            $overall === SourceStatus::Degraded ? Response::HTTP_SERVICE_UNAVAILABLE : Response::HTTP_OK,
        );
    }
}
