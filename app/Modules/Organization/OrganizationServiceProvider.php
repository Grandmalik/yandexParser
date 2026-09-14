<?php

declare(strict_types=1);

namespace App\Modules\Organization;

use App\Modules\Organization\Application\Contracts\OrganizationCatalog;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Organization\Infrastructure\Persistence\EloquentOrganizationCatalog;
use App\Modules\Organization\Infrastructure\Persistence\EloquentOrganizationDirectory;
use App\Modules\Organization\Infrastructure\Persistence\EloquentOrganizationRepository;
use App\Modules\Shared\Interfaces\Http\AuthenticatedUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class OrganizationServiceProvider extends ServiceProvider
{
    public const string CONNECT_RATE_LIMITER = 'organization.connect';

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        OrganizationRepository::class => EloquentOrganizationRepository::class,
        OrganizationDirectory::class => EloquentOrganizationDirectory::class,
        OrganizationCatalog::class => EloquentOrganizationCatalog::class,
    ];

    public function boot(): void
    {
        RateLimiter::for(self::CONNECT_RATE_LIMITER, static fn (Request $request): Limit => new Limit(
            key: (string) AuthenticatedUser::id($request),
            maxAttempts: config()->integer('organization.connect.max_attempts'),
            decaySeconds: config()->integer('organization.connect.decay_seconds'),
        ));
    }
}
