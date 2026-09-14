<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Source;

use App\Modules\Shared\Domain\HasLabel;

/**
 * Картографическая площадка, на которой живёт карточка организации.
 * Добавление площадки — это её адаптер и запись в `config/scraping.php`.
 */
enum Platform: string implements HasLabel
{
    case Yandex = 'yandex';

    public function labelKey(): string
    {
        return 'shared.enums.platform.'.$this->value;
    }
}
