<?php

declare(strict_types=1);

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\TypeScriptTransformerServiceProvider::class,

    // Модули. Shared идёт первым: остальные опираются на его биндинги.
    App\Modules\Shared\SharedServiceProvider::class,
    App\Modules\Identity\IdentityServiceProvider::class,
    App\Modules\Organization\OrganizationServiceProvider::class,
    App\Modules\Review\ReviewServiceProvider::class,
    App\Modules\Sync\SyncServiceProvider::class,
    App\Modules\Scraping\ScrapingServiceProvider::class,
];
