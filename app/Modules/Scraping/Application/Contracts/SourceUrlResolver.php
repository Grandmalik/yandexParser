<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;

/**
 * Превращает вставленную пользователем ссылку в карточку организации, на которую она указывает.
 */
interface SourceUrlResolver
{
    /**
     * Разбирает ссылку: определяет площадку, при необходимости раскрывает короткую ссылку.
     *
     * @throws InvalidSourceUrl
     * @throws SourceUnavailable если короткую ссылку надо раскрыть, а площадка не отвечает
     */
    public function resolve(string $url): ResolvedSource;
}
