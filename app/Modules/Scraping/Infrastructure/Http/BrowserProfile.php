<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

/**
 * Согласованный набор заголовков браузера. Закрепляется за сессией: меняющийся между запросами одной сессии
 * User-Agent — классический признак бота.
 */
final readonly class BrowserProfile
{
    /**
     * @param  array<string, string>  $headers  Заголовки, которые идут вместе с User-Agent (Accept-Language, клиент-хинты).
     */
    public function __construct(
        public string $userAgent,
        public array $headers,
    ) {}

    /**
     * Все заголовки профиля вместе с User-Agent.
     *
     * @return array<string, string>
     */
    public function allHeaders(): array
    {
        return ['User-Agent' => $this->userAgent, ...$this->headers];
    }
}
