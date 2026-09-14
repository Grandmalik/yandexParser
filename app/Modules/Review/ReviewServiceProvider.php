<?php

declare(strict_types=1);

namespace App\Modules\Review;

use App\Modules\Review\Application\Contracts\ReviewCatalog;
use App\Modules\Review\Application\Contracts\ReviewStore;
use App\Modules\Review\Infrastructure\Persistence\EloquentReviewCatalog;
use App\Modules\Review\Infrastructure\Persistence\EloquentReviewStore;
use Illuminate\Support\ServiceProvider;

final class ReviewServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        ReviewStore::class => EloquentReviewStore::class,
        ReviewCatalog::class => EloquentReviewCatalog::class,
    ];
}
