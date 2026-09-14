<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Review\Application\Contracts\IncomingReview;
use App\Modules\Review\Application\Contracts\ReviewStore;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->organizationId = organizationFor($this->user->id);
    $this->actingAs($this->user)->fromSpa();
});

/**
 * @return list<IncomingReview>
 */
function storedReviews(int $count, string $prefix = 'r'): array
{
    return array_map(fn (int $index): IncomingReview => new IncomingReview(
        externalId: "{$prefix}{$index}",
        authorName: "Автор {$index}",
        // В API новые сверху, поэтому больший индекс означает более позднюю публикацию.
        publishedAt: new DateTimeImmutable("2026-09-01 12:00:00 +{$index} minutes"),
        text: "Отзыв {$index}",
        rating: 5,
        businessReply: $index === 1 ? 'Спасибо!' : null,
    ), range(1, $count));
}

function store(string $organizationId, array $reviews): void
{
    app(ReviewStore::class)->upsert($organizationId, null, $reviews, new DateTimeImmutable);
}

it('serves reviews newest first, in pages of the configured size', function (): void {
    store($this->organizationId, storedReviews(120));

    $first = $this->getJson(route('api.v1.organizations.reviews.index', $this->organizationId))
        ->assertOk()
        ->assertJsonPath('meta.per_page', config('reviews.per_page'))
        ->assertJsonPath('meta.total', 120)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonCount(50, 'data');

    expect($first->json('data.0.author_name'))->toBe('Автор 120')
        ->and($first->json('data.0.rating'))->toBe(5)
        ->and($first->json('data.0.text'))->toBe('Отзыв 120');

    $last = $this->getJson(route('api.v1.organizations.reviews.index', [$this->organizationId, 'page' => 3]))
        ->assertOk()
        ->assertJsonCount(20, 'data');

    expect($last->json('data.19.author_name'))->toBe('Автор 1')
        ->and($last->json('data.19.business_reply'))->toBe('Спасибо!');
});

it('ignores a page size chosen by the client', function (): void {
    store($this->organizationId, storedReviews(60));

    $this->getJson(route('api.v1.organizations.reviews.index', [$this->organizationId, 'per_page' => 5]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', config('reviews.per_page'))
        ->assertJsonCount(50, 'data');
});

it('hides reviews the platform no longer shows', function (): void {
    store($this->organizationId, storedReviews(3));
    DB::table('reviews')->where('external_id', 'r2')->update(['removed_at' => now()]);

    $response = $this->getJson(route('api.v1.organizations.reviews.index', $this->organizationId))->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and(array_column($response->json('data'), 'author_name'))->not->toContain('Автор 2');
});

it('answers an empty page beyond the last one with correct totals', function (): void {
    store($this->organizationId, storedReviews(3));

    $this->getJson(route('api.v1.organizations.reviews.index', [$this->organizationId, 'page' => 9]))
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 3);
});

it('rejects a malformed page number', function (): void {
    $this->getJson(route('api.v1.organizations.reviews.index', [$this->organizationId, 'page' => 0]))
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed');
});

it('hides reviews of organizations the user has no access to', function (): void {
    $stranger = organizationFor(User::factory()->create()->id, '52335293875');
    store($stranger, storedReviews(3));

    $this->getJson(route('api.v1.organizations.reviews.index', $stranger))->assertNotFound();
});

it('requires authentication', function (): void {
    auth()->logout();

    $this->getJson(route('api.v1.organizations.reviews.index', $this->organizationId))->assertUnauthorized();
});
