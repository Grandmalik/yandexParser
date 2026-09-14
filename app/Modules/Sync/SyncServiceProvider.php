<?php

declare(strict_types=1);

namespace App\Modules\Sync;

use App\Modules\Organization\Application\Contracts\Events\OrganizationConnected;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Scraping\Application\Contracts\RetryPolicy;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Shared\Interfaces\Http\AppConfig\AppConfigSection;
use App\Modules\Shared\Interfaces\Http\AuthenticatedUser;
use App\Modules\Sync\Application\Contracts\Events\SyncRunStateChanged;
use App\Modules\Sync\Application\Port\SyncRunner;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Application\ScheduleResync;
use App\Modules\Sync\Application\SyncRetry;
use App\Modules\Sync\Domain\CompletenessPolicy;
use App\Modules\Sync\Domain\SyncRunRepository;
use App\Modules\Sync\Infrastructure\Broadcasting\BroadcastSyncRunState;
use App\Modules\Sync\Infrastructure\Listeners\LogSyncDuration;
use App\Modules\Sync\Infrastructure\Listeners\StartSyncOnOrganizationConnected;
use App\Modules\Sync\Infrastructure\Persistence\EloquentSyncRunRepository;
use App\Modules\Sync\Infrastructure\Queue\QueueSyncRunner;
use App\Modules\Sync\Interfaces\Console\ResyncDueOrganizationsCommand;
use App\Modules\Sync\Interfaces\Http\AppConfig\SyncConfigSection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class SyncServiceProvider extends ServiceProvider
{
    public const string MANUAL_RATE_LIMITER = 'sync.manual';

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        SyncRunRepository::class => EloquentSyncRunRepository::class,
        SyncRunner::class => QueueSyncRunner::class,
    ];

    public function register(): void
    {
        $this->app->tag([SyncConfigSection::class], AppConfigSection::TAG);

        $this->app->bind(CompletenessPolicy::class, static fn (): CompletenessPolicy => new CompletenessPolicy(
            config()->float('sync.completeness_tolerance'),
        ));

        $this->app->bind(SyncRetry::class, static fn (Application $app): SyncRetry => new SyncRetry(
            $app->make(RetryPolicy::class),
            config()->integer('sync.max_attempts'),
        ));

        $this->app->bind(LogSyncDuration::class, static fn (): LogSyncDuration => new LogSyncDuration(
            config()->integer('sync.slow_after_seconds'),
        ));

        $this->app->bind(ScheduleResync::class, static fn (Application $app): ScheduleResync => new ScheduleResync(
            organizations: $app->make(OrganizationDirectory::class),
            runs: $app->make(SyncRunRepository::class),
            sources: $app->make(SourceAvailability::class),
            requestSync: $app->make(RequestSync::class),
            clock: $app->make(ClockInterface::class),
            intervalMinutes: config()->integer('sync.resync.interval_minutes'),
            batchSize: config()->integer('sync.resync.batch_size'),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ResyncDueOrganizationsCommand::class]);
        }

        Event::listen(OrganizationConnected::class, StartSyncOnOrganizationConnected::class);
        Event::listen(SyncRunStateChanged::class, BroadcastSyncRunState::class);
        Event::listen(SyncRunStateChanged::class, LogSyncDuration::class);

        RateLimiter::for(self::MANUAL_RATE_LIMITER, static fn (Request $request): Limit => new Limit(
            key: (string) AuthenticatedUser::id($request),
            maxAttempts: config()->integer('sync.manual.max_attempts'),
            decaySeconds: config()->integer('sync.manual.decay_seconds'),
        ));
    }
}
