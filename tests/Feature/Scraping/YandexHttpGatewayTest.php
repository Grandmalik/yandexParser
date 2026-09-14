<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceReview;
use App\Modules\Scraping\Infrastructure\Yandex\Signing\RequestSigner;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const ORGANIZATION_PAGE_URL = 'https://yandex.ru/maps/org/*';
const REVIEWS_API_URL = 'https://yandex.ru/maps/api/business/fetchReviews*';

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->source = new SourceReference(Platform::Yandex, '1703836794');
    $this->gateway = app(ReviewSourceGateway::class);
});

function html(string $body, int $status = 200): GuzzleHttp\Promise\PromiseInterface
{
    return Http::response($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
}

/**
 * @param  array<array-key, mixed>|string  $body
 */
function api(array|string $body, int $status = 200): GuzzleHttp\Promise\PromiseInterface
{
    return Http::response($body, $status, ['Content-Type' => 'application/json']);
}

/**
 * Записанная страница отзывов, у которой `params.count` подменён — будто у организации отзывов больше.
 *
 * @return array<array-key, mixed>
 */
function reviewsPageWithCount(int $page, int $count): array
{
    $body = recordedJson("Yandex/reviews_page_{$page}.json");
    $body['data']['params']['count'] = $count;

    return $body;
}

/**
 * @return list<ReviewPage>
 */
function collectPages(iterable $pages): array
{
    return [...$pages];
}

describe('organization card', function (): void {
    it('reads the name, address, average rating and both counters', function (): void {
        Http::fake([ORGANIZATION_PAGE_URL => html(recordedResponse('Yandex/organization_page.html'))]);

        $organization = $this->gateway->fetchOrganization($this->source);

        expect($organization->name)->toBe('Вольт 11')
            ->and($organization->address)->toBe('ул. Савина, 20, Сыктывкар')
            ->and($organization->rating)->toBe(4.5)
            ->and($organization->ratingsCount)->toBe(416)
            ->and($organization->reviewsCount)->toBe(191);

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://yandex.ru/maps/org/1703836794/');
    });

    it('reports an organization the platform does not know', function (): void {
        Http::fake([ORGANIZATION_PAGE_URL => html(recordedResponse('Yandex/organization_page_not_found.html'))]);

        $this->gateway->fetchOrganization($this->source);
    })->throws(SourceNotFound::class);

    it('detects a captcha page instead of the card', function (): void {
        // Синтетика: во время исследования капчу нам ни разу не показали, маркеры взяты с публичной страницы капчи Яндекса.
        Http::fake([ORGANIZATION_PAGE_URL => html('<html><body><form action="/checkcaptcha"><div class="SmartCaptcha"></div></form></body></html>')]);

        $this->gateway->fetchOrganization($this->source);
    })->throws(SourceBlocked::class);

    it('reports a changed page layout and keeps the page for investigation', function (): void {
        Http::fake([ORGANIZATION_PAGE_URL => html('<html><body><div id="root"></div></body></html>')]);

        expect(fn () => $this->gateway->fetchOrganization($this->source))
            ->toThrow(fn (SourceDrift $drift) => expect($drift->stage)->toBe(DriftStage::OrganizationPage));

        $this->assertDatabaseHas('source_payloads', ['platform' => 'yandex', 'kind' => 'organization_page']);
    });

    it('reports changed rating data as a drift instead of saving garbage', function (): void {
        $page = str_replace('"ratingValue":4.5', '"ratingValue":"4,5"', recordedResponse('Yandex/organization_page.html'));
        Http::fake([ORGANIZATION_PAGE_URL => html($page)]);

        expect(fn () => $this->gateway->fetchOrganization($this->source))
            ->toThrow(fn (SourceDrift $drift) => expect($drift->expectation)->toContain('ratingData.ratingValue'));
    });

    it('treats server errors and dropped connections as a temporary outage', function (Closure $response): void {
        Http::fake([ORGANIZATION_PAGE_URL => $response]);

        $this->gateway->fetchOrganization($this->source);
    })->with([
        'server error' => [fn () => html('oops', 503)],
        'connection failure' => [fn () => Http::failedConnection()],
    ])->throws(SourceUnavailable::class);

    it('reports throttling with the delay the platform asks for', function (): void {
        Http::fake([ORGANIZATION_PAGE_URL => Http::response('', 429, ['Retry-After' => '120'])]);

        expect(fn () => $this->gateway->fetchOrganization($this->source))
            ->toThrow(fn (SourceRateLimited $exception) => expect($exception->retryAfterSeconds)->toBe(120));
    });
});

describe('reviews', function (): void {
    it('collects every review page by page with signed requests', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(recordedJson('Yandex/reviews_page_1.json'))
            ->push(recordedJson('Yandex/reviews_page_2.json'))
            ->push(recordedJson('Yandex/reviews_page_3.json'))
            ->push(recordedJson('Yandex/reviews_page_4.json')),
        ]);

        $pages = collectPages($this->gateway->fetchReviews($this->source));
        $reviews = array_merge(...array_map(fn (ReviewPage $page): array => $page->reviews, $pages));

        expect($pages)->toHaveCount(4)
            ->and(array_map(fn (ReviewPage $page): int => $page->expectedTotal, $pages))->each->toBe(191)
            ->and($reviews)->toHaveCount(191)
            ->and(array_unique(array_map(fn (SourceReview $review): string => $review->externalId, $reviews)))->toHaveCount(191);

        // Последняя страница распознаётся как последняя: запроса за пустой пятой не будет.
        Http::assertSentCount(5);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $signature = $query['s'];
            unset($query['s']);

            return app(RequestSigner::class)->signedQuery($query) === explode('?', $request->url(), 2)[1]
                && $signature !== '';
        });
    });

    it('maps each review to author, date, text, rating and business reply', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(reviewsPageWithCount(1, 50)),
        ]);

        $review = collectPages($this->gateway->fetchReviews($this->source))[0]->reviews[0];
        $recorded = recordedJson('Yandex/reviews_page_1.json')['data']['reviews'][0];

        expect($review->externalId)->toBe($recorded['reviewId'])
            ->and($review->authorName)->toBe($recorded['author']['name'])
            ->and($review->rating)->toBe($recorded['rating'])
            ->and($review->text)->toBe(trim($recorded['text']))
            ->and($review->businessReply)->toBe(isset($recorded['businessComment']) ? trim($recorded['businessComment']['text']) : null)
            ->and($review->publishedAt->getTimezone()->getName())->toBe('UTC')
            ->and($review->publishedAt->format(DATE_ATOM))->toBe((new DateTimeImmutable($recorded['updatedTime']))->format(DATE_ATOM));
    });

    it('keeps legacy reviews without an author or a star rating', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(reviewsPageWithCount(4, 41)),
        ]);

        $reviews = collectPages($this->gateway->fetchReviews($this->source))[0]->reviews;
        $legacy = array_values(array_filter($reviews, fn (SourceReview $review): bool => $review->externalId === 'ugc_78467ef9d5f59665478b0205c5722ae0'))[0];

        expect($reviews)->toHaveCount(41)
            ->and($legacy->authorName)->toBeNull()
            ->and($legacy->rating)->toBeNull()
            ->and($legacy->businessReply)->toBe('Благодарим за ваш отзыв')
            ->and($legacy->publishedAt->format(DATE_ATOM))->toBe('2017-02-16T06:19:55+00:00');
    });

    it('caps the expected total at what the platform serves', function (): void {
        config(['scraping.platforms.yandex.reviews.max_available' => 100]);
        app()->forgetInstance(App\Modules\Scraping\Infrastructure\Yandex\YandexSettings::class);
        app()->forgetInstance(ReviewSourceGateway::class);

        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(reviewsPageWithCount(1, 5858))
            ->push(reviewsPageWithCount(2, 5858)),
        ]);

        $pages = collectPages(app(ReviewSourceGateway::class)->fetchReviews($this->source));

        expect($pages)->toHaveCount(2)->and($pages[0]->expectedTotal)->toBe(100);
    });

    it('ends the stream when the platform stops serving pages earlier than expected', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(reviewsPageWithCount(1, 5858))
            ->push(recordedJson('Yandex/reviews_beyond_limit.json')),
        ]);

        $pages = collectPages($this->gateway->fetchReviews($this->source));

        expect($pages)->toHaveCount(1)->and($pages[0]->expectedTotal)->toBe(600);
    });

    it('ends the stream on an empty page', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(reviewsPageWithCount(4, 5858))
            ->push(recordedJson('Yandex/reviews_page_5.json')),
        ]);

        expect(collectPages($this->gateway->fetchReviews($this->source)))->toHaveCount(1);
    });

    it('returns nothing for an organization without reviews', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(recordedJson('Yandex/reviews_unknown_business.json')),
        ]);

        expect(collectPages($this->gateway->fetchReviews($this->source)))->toBe([]);
    });

    it('renews an expired token once and continues', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(['csrfToken' => 'renewed-token:1789150999'])
            ->push(reviewsPageWithCount(1, 50)),
        ]);

        expect(collectPages($this->gateway->fetchReviews($this->source))[0]->reviews)->toHaveCount(50);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'csrfToken=renewed-token%3A1789150999'));
    });

    it('reports a drift when the token keeps being renewed instead of data', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(['csrfToken' => 'first:1'])
            ->push(['csrfToken' => 'second:2']),
        ]);

        collectPages($this->gateway->fetchReviews($this->source));
    })->throws(SourceDrift::class);

    it('reports how the source changed', function (Closure $response, DriftStage $stage): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->pushResponse($response()),
        ]);

        expect(fn () => collectPages($this->gateway->fetchReviews($this->source)))
            ->toThrow(fn (SourceDrift $drift) => expect($drift->stage)->toBe($stage));

        $this->assertDatabaseHas('source_payloads', ['kind' => $stage->value]);
    })->with([
        'signature rejected' => [fn () => Http::response('Bad Request', 400, ['Content-Type' => 'text/plain']), DriftStage::Signature],
        'required parameter added' => [fn () => api(recordedJson('Yandex/reviews_validation_error.json')), DriftStage::ReviewsRequest],
        'not JSON any more' => [fn () => html('<html></html>'), DriftStage::ReviewsPage],
        'review field renamed' => [function () {
            $body = recordedJson('Yandex/reviews_page_1.json');
            $body['data']['reviews'][3]['reviewId'] = null;

            return api($body);
        }, DriftStage::ReviewsPage],
        'reviews missing while counted' => [function () {
            $body = recordedJson('Yandex/reviews_page_1.json');
            $body['data']['reviews'] = [];

            return api($body);
        }, DriftStage::ReviewsPage],
    ]);

    it('names the broken field in the drift report', function (): void {
        $body = recordedJson('Yandex/reviews_page_1.json');
        $body['data']['reviews'][3]['rating'] = 7;
        Http::fake([REVIEWS_API_URL => Http::sequence()->push(recordedJson('Yandex/reviews_token.json'))->push($body)]);

        expect(fn () => collectPages($this->gateway->fetchReviews($this->source)))
            ->toThrow(fn (SourceDrift $drift) => expect($drift->expectation)->toBe('data.reviews.3.rating must be 0 (no rating) or within 1..5'));
    });

    it('treats an error on the first page as a temporary outage', function (): void {
        Http::fake([REVIEWS_API_URL => Http::sequence()
            ->push(recordedJson('Yandex/reviews_token.json'))
            ->push(recordedJson('Yandex/reviews_beyond_limit.json')),
        ]);

        collectPages($this->gateway->fetchReviews($this->source));
    })->throws(SourceUnavailable::class);

    it('detects a captcha redirect of the API', function (): void {
        // Синтетика: см. тест про капчу на карточке организации.
        Http::fake([REVIEWS_API_URL => Http::response('', 302, ['Location' => 'https://yandex.ru/showcaptcha?cc=1&retpath=x'])]);

        collectPages($this->gateway->fetchReviews($this->source));
    })->throws(SourceBlocked::class);
});
