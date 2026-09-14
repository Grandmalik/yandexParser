<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

/**
 * Где именно парсер заметил, что площадка отвечает не так, как ожидалось.
 */
enum DriftStage: string
{
    case OrganizationPage = 'organization_page';
    case Token = 'token';
    /** Площадка отклонила корректно составленный запрос: вероятнее всего сменился алгоритм подписи. */
    case Signature = 'signature';
    case ReviewsRequest = 'reviews_request';
    case ReviewsPage = 'reviews_page';

    /**
     * Площадка отвергла сам запрос (подпись, токен, параметры), а не сменила формат данных.
     */
    public function isRequestLevel(): bool
    {
        return match ($this) {
            self::Token, self::Signature, self::ReviewsRequest => true,
            self::OrganizationPage, self::ReviewsPage => false,
        };
    }
}
