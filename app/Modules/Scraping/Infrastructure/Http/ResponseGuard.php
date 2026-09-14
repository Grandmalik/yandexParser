<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use Illuminate\Http\Client\Response;

/**
 * Узнаёт ответы, которые вообще не про запрошенные данные: троттлинг, капчу и бан-страницы, серверные ошибки.
 */
final readonly class ResponseGuard
{
    /**
     * @param  list<string>  $blockedMarkers  Фрагменты капчи и бан-страниц (и их адресов) в нижнем регистре.
     */
    public function __construct(private array $blockedMarkers) {}

    /**
     * Проверяет ответ и бросает осмысленную ошибку вместо того, чтобы отдать мусор дальше в парсер.
     *
     * @throws SourceRateLimited
     * @throws SourceBlocked
     * @throws SourceUnavailable
     */
    public function inspect(Response $response): void
    {
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');

            throw SourceRateLimited::retryAfter(ctype_digit($retryAfter) ? (int) $retryAfter : null);
        }

        if ($this->isCaptcha($response)) {
            throw SourceBlocked::byCaptcha();
        }

        if ($response->serverError()) {
            throw SourceUnavailable::because($response->toException());
        }
    }

    /**
     * Похож ли ответ на капчу или бан-страницу.
     */
    private function isCaptcha(Response $response): bool
    {
        // Смотрим и на адрес переадресации, и на итоговый URL: площадка уводит на капчу именно редиректом.
        $signals = [$response->header('Location'), $response->header('Content-Location'), (string) $response->effectiveUri()];

        // Ищем только в HTML: в JSON лежат пользовательские тексты, где может упоминаться что угодно.
        if (str_contains(strtolower($response->header('Content-Type')), 'html')) {
            $signals[] = $response->body();
        }

        $haystack = strtolower(implode("\n", $signals));

        foreach ($this->blockedMarkers as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }
}
