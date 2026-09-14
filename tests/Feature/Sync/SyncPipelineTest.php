<?php

declare(strict_types=1);

use App\Modules\Organization\Application\Contracts\Events\OrganizationMetricsChanged;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use App\Modules\Sync\Application\MarkSyncFailed;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Application\RunSync;
use App\Modules\Sync\Application\SyncRetry;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncTrigger;
use App\Modules\Sync\Infrastructure\Jobs\SyncOrganizationJob;
use App\Modules\Sync\Interfaces\Broadcasting\SyncRunUpdated;
use App\Modules\Sync\Interfaces\Http\Data\SyncRunData;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ScriptedSourceGateway;

beforeEach(function (): void {
    Event::fake([OrganizationMetricsChanged::class]);
    // В тестах очередь выполняет задачи сразу; здесь каждый прогон запускается явно, по одной попытке.
    Queue::fake();

    $this->organizationId = connectOrganization();
});

function connectOrganization(): string
{
    $repository = app(OrganizationRepository::class);
    $organization = $repository->saveIfAbsent(Organization::connect(
        OrganizationId::generate(),
        new SourceReference(Platform::Yandex, '1703836794'),
        'https://yandex.ru/maps/org/1703836794/',
    ));

    return $organization->id->value;
}

function platform(ScriptedSourceGateway $gateway): ScriptedSourceGateway
{
    app()->instance(ReviewSourceGateway::class, $gateway);

    return $gateway;
}

function runSync(string $organizationId): string
{
    $view = app(RequestSync::class)->handle($organizationId, SyncTrigger::Connected);
    app(RunSync::class)->handle(SyncRunId::fromString($view->id));

    return $view->id;
}

it('stores the card, its figures snapshot and every review', function (): void {
    platform(new ScriptedSourceGateway(
        pages: [[ScriptedSourceGateway::review('a'), ScriptedSourceGateway::review('b')], [ScriptedSourceGateway::review('c')]],
        expectedTotal: 3,
    ));

    $runId = runSync($this->organizationId);

    $this->assertDatabaseHas('organizations', [
        'id' => $this->organizationId,
        'name' => 'Вольт 11',
        'address' => 'ул. Савина, 20',
        'rating' => 4.5,
        'ratings_count' => 416,
        'reviews_count' => 191,
    ]);
    $this->assertDatabaseHas('organization_snapshots', ['organization_id' => $this->organizationId, 'sync_run_id' => $runId, 'ratings_count' => 416]);
    $this->assertDatabaseCount('reviews', 3);
    $this->assertDatabaseCount('review_revisions', 3);
    $this->assertDatabaseHas('sync_runs', [
        'id' => $runId,
        'status' => 'completed',
        'progress_current' => 3,
        'progress_total' => 3,
        'attempts' => 1,
        'error_code' => null,
    ]);
    expect(app('db')->table('sync_runs')->where('id', $runId)->value('stats'))
        ->toBe(json_encode(['created' => 3, 'updated' => 0, 'unchanged' => 0, 'removed' => 0]));
    Event::assertDispatched(OrganizationMetricsChanged::class);
});

it('changes nothing when the platform returns the same data again', function (): void {
    $gateway = platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a')]], expectedTotal: 1));
    runSync($this->organizationId);

    $secondRun = runSync($this->organizationId);

    $this->assertDatabaseCount('reviews', 1);
    $this->assertDatabaseCount('review_revisions', 1);
    expect(app('db')->table('sync_runs')->where('id', $secondRun)->value('stats'))
        ->toBe(json_encode(['created' => 0, 'updated' => 0, 'unchanged' => 1, 'removed' => 0]))
        ->and($gateway->organizationCalls)->toBe(2);
});

it('keeps the previous version of a review that changed', function (): void {
    platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a', 'Сначала плохо', 2)]], expectedTotal: 1));
    runSync($this->organizationId);

    platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a', 'Исправились, спасибо', 5, 'Спасибо за отзыв!')]], expectedTotal: 1));
    $secondRun = runSync($this->organizationId);

    $this->assertDatabaseCount('reviews', 1);
    $this->assertDatabaseHas('reviews', ['external_id' => 'a', 'rating' => 5, 'text' => 'Исправились, спасибо', 'business_reply' => 'Спасибо за отзыв!']);
    $this->assertDatabaseCount('review_revisions', 2);
    $this->assertDatabaseHas('review_revisions', ['rating' => 2, 'text' => 'Сначала плохо']);
    expect(app('db')->table('sync_runs')->where('id', $secondRun)->value('stats'))
        ->toBe(json_encode(['created' => 0, 'updated' => 1, 'unchanged' => 0, 'removed' => 0]));
});

it('marks reviews the platform no longer shows as removed', function (): void {
    platform(new ScriptedSourceGateway(
        pages: [[ScriptedSourceGateway::review('a'), ScriptedSourceGateway::review('b')]],
        expectedTotal: 2,
    ));
    runSync($this->organizationId);

    $this->travel(1)->minutes();
    platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a')]], expectedTotal: 1));
    $secondRun = runSync($this->organizationId);

    $this->assertDatabaseHas('reviews', ['external_id' => 'a', 'removed_at' => null]);
    expect(app('db')->table('reviews')->where('external_id', 'b')->value('removed_at'))->not->toBeNull()
        ->and(app('db')->table('sync_runs')->where('id', $secondRun)->value('stats'))
        ->toBe(json_encode(['created' => 0, 'updated' => 0, 'unchanged' => 1, 'removed' => 1]));
});

it('reports a partial collection and keeps missing reviews untouched', function (): void {
    platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a'), ScriptedSourceGateway::review('b')]], expectedTotal: 2));
    runSync($this->organizationId);

    // Теперь площадка заявляет 600 отзывов, но отдаёт всего одну страницу.
    platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a')]], expectedTotal: 600));
    $secondRun = runSync($this->organizationId);

    $this->assertDatabaseHas('sync_runs', ['id' => $secondRun, 'status' => 'partial', 'error_code' => 'source.partial', 'progress_current' => 1, 'progress_total' => 600]);
    $this->assertDatabaseHas('reviews', ['external_id' => 'b', 'removed_at' => null]);
});

it('stores an organization without reviews as a complete collection', function (): void {
    platform(new ScriptedSourceGateway(
        organization: new SourceOrganization('Новое место', null, 0.0, 0, 0),
        pages: [],
        expectedTotal: 0,
    ));

    $runId = runSync($this->organizationId);

    $this->assertDatabaseCount('reviews', 0);
    $this->assertDatabaseHas('sync_runs', ['id' => $runId, 'status' => 'completed']);
});

describe('progress broadcasting', function (): void {
    it('announces every step to the organization channel', function (): void {
        Event::fake([SyncRunUpdated::class]);
        platform(new ScriptedSourceGateway(
            pages: [[ScriptedSourceGateway::review('a')], [ScriptedSourceGateway::review('b')]],
            expectedTotal: 2,
        ));

        runSync($this->organizationId);

        // Начали, сохранили страницу, сохранили ещё одну, завершили.
        Event::assertDispatchedTimes(SyncRunUpdated::class, 4);
        Event::assertDispatched(
            SyncRunUpdated::class,
            fn (SyncRunUpdated $event): bool => $event->broadcastOn()->name === 'private-organizations.'.$this->organizationId,
        );
    });

    it('pushes the state in the shape the endpoint answers with', function (): void {
        Event::fake([SyncRunUpdated::class]);
        platform(new ScriptedSourceGateway(pages: [[ScriptedSourceGateway::review('a')]], expectedTotal: 1));

        $runId = runSync($this->organizationId);

        Event::assertDispatched(SyncRunUpdated::class, function (SyncRunUpdated $event) use ($runId): bool {
            $run = $event->broadcastWith()['sync_run'];

            return $event->broadcastAs() === SyncRunUpdated::NAME
                && $run['id'] === $runId
                && $run['organization_id'] === $this->organizationId
                && $run['status']['value'] === 'completed'
                && $run['progress'] === ['current' => 1, 'total' => 1, 'percent' => 100];
        });
    });

    it('pushes progress immediately instead of queueing it', function (): void {
        // Воркер слушает очередь сбора; рассылка, поставленная в очередь, не была бы доставлена никогда.
        expect(new SyncRunUpdated(SyncRunData::fromSyncRunView(
            App\Modules\Sync\Application\SyncRunView::fromSyncRun(
                App\Modules\Sync\Domain\SyncRun::queue(
                    SyncRunId::generate(),
                    $this->organizationId,
                    SyncTrigger::Manual,
                    new DateTimeImmutable,
                ),
            ),
            app(App\Modules\Shared\Interfaces\Http\Errors\ErrorMessages::class),
        )))->toBeInstanceOf(Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class);
    });

    it('announces a run that gave up, so a watching screen stops waiting', function (): void {
        Event::fake([SyncRunUpdated::class]);
        platform(new ScriptedSourceGateway(failure: SourceDrift::at(DriftStage::ReviewsPage, 'a list of reviews', '{}')));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        (new SyncOrganizationJob($view->id))->handle(app(RunSync::class), app(SyncRetry::class), app(MarkSyncFailed::class));

        Event::assertDispatched(
            SyncRunUpdated::class,
            fn (SyncRunUpdated $event): bool => $event->broadcastWith()['sync_run']['status']['value'] === 'failed',
        );
    });
});

describe('failures', function (): void {
    function runJob(string $runId): void
    {
        (new SyncOrganizationJob($runId))->handle(app(RunSync::class), app(SyncRetry::class), app(MarkSyncFailed::class));
    }

    it('gives up immediately when the platform changed its format', function (): void {
        platform(new ScriptedSourceGateway(failure: SourceDrift::at(DriftStage::ReviewsPage, 'a list of reviews', '{}')));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        runJob($view->id);

        $this->assertDatabaseHas('sync_runs', ['id' => $view->id, 'status' => 'failed', 'error_code' => 'source.drift', 'attempts' => 1]);
    });

    it('keeps a run alive for another attempt when the platform is temporarily unavailable', function (): void {
        platform(new ScriptedSourceGateway(failure: SourceUnavailable::because()));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        runJob($view->id);

        $this->assertDatabaseHas('sync_runs', ['id' => $view->id, 'status' => 'running', 'attempts' => 1]);
    });

    it('fails the run when attempts run out', function (): void {
        config(['sync.max_attempts' => 1]);
        platform(new ScriptedSourceGateway(failure: SourceUnavailable::because()));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        runJob($view->id);

        $this->assertDatabaseHas('sync_runs', ['id' => $view->id, 'status' => 'failed', 'error_code' => 'source.unavailable']);
    });

    it('finishes a run that died without catching anything', function (): void {
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        (new SyncOrganizationJob($view->id))->failed(new RuntimeException('worker killed'));

        $this->assertDatabaseHas('sync_runs', ['id' => $view->id, 'status' => 'failed', 'error_code' => 'sync.internal']);
    });

    it('keeps everything collected before a failure in the middle', function (): void {
        platform(new ScriptedSourceGateway(
            pages: [[ScriptedSourceGateway::review('a')], [ScriptedSourceGateway::review('b')]],
            expectedTotal: 600,
            failure: SourceUnavailable::because(),
            failAfterPages: 2,
        ));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);

        runJob($view->id);

        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseHas('sync_runs', [
            'id' => $view->id,
            'status' => 'running',
            'progress_current' => 2,
            // Всё до второй страницы включительно сохранено, поэтому повтор обязан просить у площадки третью.
            'next_page' => 3,
        ]);
    });

    it('continues from where it stopped instead of collecting everything again', function (): void {
        $pages = [
            [ScriptedSourceGateway::review('a')],
            [ScriptedSourceGateway::review('b')],
            [ScriptedSourceGateway::review('c')],
            [ScriptedSourceGateway::review('d')],
        ];
        platform(new ScriptedSourceGateway(
            pages: $pages,
            expectedTotal: 4,
            failure: SourceUnavailable::because(),
            failAfterPages: 2,
        ));
        $view = app(RequestSync::class)->handle($this->organizationId, SyncTrigger::Connected);
        runJob($view->id);

        // На следующей попытке площадка отвечает нормально.
        $resumed = platform(new ScriptedSourceGateway(pages: $pages, expectedTotal: 4));
        runJob($view->id);

        expect($resumed->requestedFromPage)->toBe(3);
        $this->assertDatabaseCount('reviews', 4);
        $this->assertDatabaseHas('sync_runs', [
            'id' => $view->id,
            'status' => 'completed',
            'progress_current' => 4,
            'attempts' => 2,
        ]);
        // На второй попытке сохранены только две недостающие страницы, а не весь сбор заново.
        expect(app('db')->table('sync_runs')->where('id', $view->id)->value('stats'))
            ->toBe(json_encode(['created' => 2, 'updated' => 0, 'unchanged' => 0, 'removed' => 0]));
    });
});
