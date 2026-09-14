<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain;

/**
 * Энум, значения которого показываются пользователю; переводит ключ слой доставки.
 */
interface HasLabel
{
    /**
     * Ключ перевода подписи значения, например «sync.enums.status.running».
     */
    public function labelKey(): string;
}
