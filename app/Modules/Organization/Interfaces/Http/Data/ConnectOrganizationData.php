<?php

declare(strict_types=1);

namespace App\Modules\Organization\Interfaces\Http\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

/**
 * Тело запроса `POST /organizations`. Здесь проверяется только форма; ведёт ли ссылка на карточку
 * организации, решает резолвер источника.
 */
final class ConnectOrganizationData extends Data
{
    public function __construct(
        #[Max(2048)]
        public string $url,
    ) {}
}
