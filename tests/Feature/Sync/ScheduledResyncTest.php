<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Scraping\Application\Contracts\SourceAvailability;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Application\ScheduleResync;
use App\Modules\Sync\Domain\SyncTrigger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    // В тестах очередь выполняет задачи сразу; здесь проверяется только само решение поставить задачу.
    Queue::fake();
    config(['sync.resync.interval_minutes' => 60, 'sync.resync.batch_size' => 10]);

    $this->user = User::factory()->create();
});

/**
 * Организация, чьи показатели последний раз читались в указанный момент; null — не читались никогда.
 */
function organizationReadAt(int $userId, ?string $readAt, string $externalId): string
{
    $id = organizationFor($userId, $externalId);
    DB::table('organizations')->where('id', $id)->update(['metrics_updated_at' => $readAt]);

    return $id;
}

function availabilityReturning(?int $pausedFor): void
{
    app()->instance(SourceAvailability::class, new class($pausedFor) implements SourceAvailability
    {
        public function __construct(private readonly ?int $pausedFor) {}

        public function pausedFor(Platform $platform): ?int
        {
            return $this->pausedFor;
        }
    });
}

function resync(): App\Modules\Sync\Application\ResyncSummary
{
    return app(ScheduleResync::class)->handle();
}

it('collects organizations that were never read and those read long ago', function (): void {
    availabilityReturning(null);
    $never = organizationReadAt($this->user->id, null, '1000000001');
    $stale = organizationReadAt($this->user->id, now()->subDay()->toDateTimeString(), '1000000002');
    organizationReadAt($this->user->id, now()->subMinutes(5)->toDateTimeString(), '1000000003');

    $summary = resync();

    expect($summary->due)->toBe(2)->and($summary->queued)->toBe(2);
    $this->assertDatabaseHas('sync_runs', ['organization_id' => $never, 'trigger' => SyncTrigger::Scheduled->value]);
    $this->assertDatabaseHas('sync_runs', ['organization_id' => $stale, 'trigger' => SyncTrigger::Scheduled->value]);
    $this->assertDatabaseCount('sync_runs', 2);
});

it('takes only a batch per tick, oldest first, so a network of branches is spread over time', function (): void {
    availabilityReturning(null);
    config(['sync.resync.batch_size' => 1]);
    organizationReadAt($this->user->id, now()->subHours(2)->toDateTimeString(), '1000000001');
    $oldest = organizationReadAt($this->user->id, null, '1000000002');

    $summary = resync();

    expect($summary->due)->toBe(1)->and($summary->queued)->toBe(1);
    // «Не читалась ни разу» идёт раньше «читалась два часа назад»: она ждала дольше всех.
    $this->assertDatabaseHas('sync_runs', ['organization_id' => $oldest]);
    $this->assertDatabaseCount('sync_runs', 1);
});

it('leaves an organization alone while a collection of it is already going', function (): void {
    availabilityReturning(null);
    $organizationId = organizationReadAt($this->user->id, null, '1000000001');
    app(RequestSync::class)->handle($organizationId, SyncTrigger::Manual);

    $summary = resync();

    expect($summary->alreadyRunning)->toBe(1)->and($summary->queued)->toBe(0);
    $this->assertDatabaseCount('sync_runs', 1);
});

it('queues nothing while the platform refuses us', function (): void {
    availabilityReturning(900);
    organizationReadAt($this->user->id, null, '1000000001');

    $summary = resync();

    expect($summary->platformPaused)->toBe(1)->and($summary->queued)->toBe(0);
    $this->assertDatabaseCount('sync_runs', 0);
});

it('reports what it did through the command', function (): void {
    availabilityReturning(null);
    organizationReadAt($this->user->id, null, '1000000001');

    $this->artisan('sync:resync-due')
        ->expectsOutputToContain('Due: 1, queued: 1, already running: 0, platform paused: 0.')
        ->assertSuccessful();
});
