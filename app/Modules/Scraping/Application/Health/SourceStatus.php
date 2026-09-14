<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Health;

use App\Modules\Shared\Domain\HasLabel;

/**
 * Вердикт о площадке.
 */
enum SourceStatus: string implements HasLabel
{
    /** Последние чтения удавались достаточно часто, чтобы адаптеру доверять. */
    case Healthy = 'healthy';
    /** Слишком много свежих сбоев или площадка сменила формат: парсером надо заняться. */
    case Degraded = 'degraded';
    /** Площадка нас не пускает, обращения приостановлены (circuit breaker). */
    case Paused = 'paused';

    public function labelKey(): string
    {
        return 'scraping.enums.source_status.'.$this->value;
    }
}
