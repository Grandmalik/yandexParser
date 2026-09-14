<?php

declare(strict_types=1);

use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Identity\Interfaces\Http\Controllers\CurrentUserController;
use App\Modules\Identity\Interfaces\Http\Controllers\SessionController;
use App\Modules\Organization\Interfaces\Http\Controllers\OrganizationController;
use App\Modules\Organization\OrganizationServiceProvider;
use App\Modules\Review\Interfaces\Http\Controllers\ReviewController;
use App\Modules\Scraping\Interfaces\Http\Controllers\HealthController;
use App\Modules\Shared\Interfaces\Http\AppConfig\ShowAppConfigController;
use App\Modules\Sync\Interfaces\Http\Controllers\SyncRunController;
use App\Modules\Sync\SyncServiceProvider;
use Illuminate\Support\Facades\Route;

/*
| API приложения, версионируемое целиком. Префикс «api» и группа middleware приходят из bootstrap/app.php.
| Разделы идут по тем bounded contexts, которым принадлежат контроллеры.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {

        /*
        | Публичное: параметры, которые SPA не имеет права зашивать, и проверка живости — она обязана
        | работать без сессии.
        */
        Route::get('app-config', ShowAppConfigController::class)->name('app-config.show');
        Route::get('health', HealthController::class)->name('health.show');

        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('login', [SessionController::class, 'store'])
                ->middleware('throttle:'.IdentityServiceProvider::LOGIN_RATE_LIMITER)
                ->name('login');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
                Route::get('me', CurrentUserController::class)->name('me');
            });
        });

        Route::middleware('auth:sanctum')->group(function (): void {

            Route::post('organizations', [OrganizationController::class, 'store'])
                ->middleware('throttle:'.OrganizationServiceProvider::CONNECT_RATE_LIMITER)
                ->name('organizations.store');

            Route::get('organizations', [OrganizationController::class, 'index'])->name('organizations.index');

            Route::prefix('organizations/{organization}')->whereUuid('organization')->group(function (): void {
                Route::get('/', [OrganizationController::class, 'show'])->name('organizations.show');
                Route::get('history', [OrganizationController::class, 'history'])->name('organizations.history');
                Route::get('reviews', [ReviewController::class, 'index'])->name('organizations.reviews.index');

                Route::post('sync-runs', [SyncRunController::class, 'store'])
                    ->middleware('throttle:'.SyncServiceProvider::MANUAL_RATE_LIMITER)
                    ->name('organizations.sync-runs.store');

                Route::get('sync-runs/latest', [SyncRunController::class, 'latest'])
                    ->name('organizations.sync-runs.latest');
            });

            Route::get('sync-runs/{syncRun}', [SyncRunController::class, 'show'])
                ->whereUuid('syncRun')
                ->name('sync-runs.show');
        });
    });
