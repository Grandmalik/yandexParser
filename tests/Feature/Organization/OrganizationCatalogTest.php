<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Organization\Domain\RatingSummary;
use App\Modules\Review\Application\Contracts\IncomingReview;
use App\Modules\Review\Application\Contracts\ReviewStore;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organizationId = organizationFor($this->user->id);
    $this->actingAs($this->user)->fromSpa();
});

function withFigures(string $organizationId, float $rating, int $ratings, int $reviews, string $capturedAt): void
{
    $repository = app(OrganizationRepository::class);
    $id = OrganizationId::fromString($organizationId);
    $organization = $repository->findById($id);
    $summary = new RatingSummary($rating, $ratings, $reviews);

    $organization->describe('Вольт 11', 'ул. Савина, 20');
    $organization->recordFigures($summary, new DateTimeImmutable($capturedAt));
    $repository->save($organization);
    $repository->addSnapshot($id, null, $summary, new DateTimeImmutable($capturedAt));
}

it('lists the organizations of the current user only', function (): void {
    organizationFor(User::factory()->create()->id, '52335293875');

    $response = $this->getJson(route('api.v1.organizations.index'))->assertOk()->assertJsonCount(1, 'data');

    expect($response->json('data.0.id'))->toBe($this->organizationId);
});

/**
 * Подключает текущему пользователю указанное число организаций — вдобавок к той, что создана в beforeEach.
 */
function connectMany(int $userId, int $total): void
{
    for ($number = 2; $number <= $total; $number++) {
        organizationFor($userId, (string) $number);
    }
}

it('serves organizations in pages of the configured size', function (): void {
    connectMany($this->user->id, 120);

    $first = $this->getJson(route('api.v1.organizations.index'))
        ->assertOk()
        ->assertJsonPath('meta.per_page', config('organization.per_page'))
        ->assertJsonPath('meta.total', 120)
        ->assertJsonCount(50, 'data');

    $last = $this->getJson(route('api.v1.organizations.index', ['page' => 3]))
        ->assertOk()
        ->assertJsonCount(20, 'data');

    // Порядок устойчив, поэтому ни одна организация не попадёт на две страницы, а другая — никуда.
    expect(array_intersect(array_column($first->json('data'), 'id'), array_column($last->json('data'), 'id')))
        ->toBe([]);
});

it('ignores a page size chosen by the client', function (): void {
    connectMany($this->user->id, 60);

    $this->getJson(route('api.v1.organizations.index', ['per_page' => 5]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', config('organization.per_page'))
        ->assertJsonCount(50, 'data');
});

it('rejects a malformed page number', function (): void {
    $this->getJson(route('api.v1.organizations.index', ['page' => 0]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed');
});

it('shows the platform figures and how many reviews we actually have', function (): void {
    withFigures($this->organizationId, 4.5, 416, 191, '2026-09-12 10:00:00');
    app(ReviewStore::class)->upsert($this->organizationId, null, [
        new IncomingReview('a', 'Автор', new DateTimeImmutable, 'Текст', 5, null),
    ], new DateTimeImmutable);

    $this->getJson(route('api.v1.organizations.show', $this->organizationId))
        ->assertOk()
        ->assertJsonPath('data.name', 'Вольт 11')
        ->assertJsonPath('data.address', 'ул. Савина, 20')
        ->assertJsonPath('data.platform.value', 'yandex')
        ->assertJsonPath('data.rating_summary', ['rating' => 4.5, 'ratings_count' => 416, 'reviews_count' => 191])
        ->assertJsonPath('data.reviews_stored', 1);
});

it('shows a freshly connected organization without figures', function (): void {
    $this->getJson(route('api.v1.organizations.show', $this->organizationId))
        ->assertOk()
        ->assertJsonPath('data.rating_summary', null)
        ->assertJsonPath('data.reviews_stored', 0);
});

it('tells how the figures changed between syncs, newest first', function (): void {
    withFigures($this->organizationId, 4.4, 400, 180, '2026-09-10 10:00:00');
    withFigures($this->organizationId, 4.5, 416, 191, '2026-09-12 10:00:00');

    $history = $this->getJson(route('api.v1.organizations.history', $this->organizationId))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    expect($history[0]['rating'])->toBe(4.5)
        ->and($history[0]['rating_change'])->toBe(0.1)
        ->and($history[0]['ratings_count_change'])->toBe(16)
        ->and($history[0]['reviews_count_change'])->toBe(11)
        ->and($history[1]['rating_change'])->toBeNull();
});

it('hides organizations of other users', function (Closure $request): void {
    $stranger = organizationFor(User::factory()->create()->id, '52335293875');

    $request($this, $stranger)->assertNotFound();
})->with([
    'card' => [fn ($test, string $organization) => $test->getJson(route('api.v1.organizations.show', $organization))],
    'history' => [fn ($test, string $organization) => $test->getJson(route('api.v1.organizations.history', $organization))],
]);

it('requires authentication', function (): void {
    auth()->logout();

    $this->getJson(route('api.v1.organizations.index'))->assertUnauthorized();
});
