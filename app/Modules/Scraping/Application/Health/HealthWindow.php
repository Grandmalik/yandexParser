<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Health;

use App\Modules\Shared\Domain\Source\Platform;

/**
 * Последние исходы чтения площадки, общие для всех воркеров. Здоровье — операционный сигнал, а не запись:
 * потеря окна (например, при сбросе кэша) означает лишь, что вердикт будет строиться по следующим запросам.
 */
interface HealthWindow
{
    /**
     * Записывает исход одного чтения.
     *
     * @param  string  $outcome  «ok» либо код ошибки.
     */
    public function record(Platform $platform, string $outcome): void;

    /**
     * Последние исходы, свежие сверху, не больше размера окна из конфигурации.
     *
     * @return list<string>
     */
    public function recent(Platform $platform): array;
}
