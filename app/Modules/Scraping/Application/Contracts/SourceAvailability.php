<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Shared\Domain\Source\Platform;

/**
 * Можно ли вообще сейчас обращаться к площадке. Работа, которая может подождать (плановый ресинк), спрашивает
 * заранее, вместо того чтобы ставить запросы, которые анти-бан всё равно отклонит.
 */
interface SourceAvailability
{
    /**
     * Через сколько секунд к площадке снова можно обращаться; null — можно прямо сейчас.
     */
    public function pausedFor(Platform $platform): ?int;
}
