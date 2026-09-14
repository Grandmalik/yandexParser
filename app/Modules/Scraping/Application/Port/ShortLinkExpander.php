<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Port;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Domain\Url\SourceUrl;

/**
 * Раскрытие коротких ссылок площадки.
 */
interface ShortLinkExpander
{
    /**
     * Проходит ровно один редирект; null — если ссылка никуда осмысленно не ведёт.
     *
     * @throws SourceUnavailable
     */
    public function expand(SourceUrl $url): ?SourceUrl;
}
