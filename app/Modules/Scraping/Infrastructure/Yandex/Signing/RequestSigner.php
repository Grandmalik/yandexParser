<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex\Signing;

/**
 * Собирает строку запроса к внутреннему API Яндекс Карт вместе с её подписью.
 */
interface RequestSigner
{
    /**
     * Подписанная строка запроса.
     *
     * @param  array<string, string>  $parameters
     */
    public function signedQuery(array $parameters): string;
}
