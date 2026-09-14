<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\SourceReview;
use App\Modules\Shared\Domain\Source\SourceReference;
use Generator;
use Illuminate\Support\Facades\Log;

/**
 * Превращает сырые страницы отзывов Яндекса (узлы `data`, как бы они ни были получены) в поток ReviewPage:
 * маппинг, дедупликация на стыках страниц, ожидаемое общее количество и решение, когда остановиться.
 */
final readonly class YandexReviewPages
{
    public function __construct(
        private YandexResponseMapper $mapper,
        private YandexSettings $settings,
    ) {}

    /**
     * @param  iterable<int, ?PayloadReader>  $pages  Узлы `data` по порядку страниц, вытягиваются лениво; null — страницы кончились.
     * @param  int  $firstPage  С какого номера начинается поток, чтобы возобновлённый сбор продолжил счёт, а не начал заново.
     * @return Generator<int, ReviewPage>
     *
     * @throws SourceDrift
     * @throws SourceUnavailable
     */
    public function assemble(iterable $pages, SourceReference $source, int $firstPage = 1): Generator
    {
        $seen = [];
        $page = max(1, $firstPage) - 1;

        foreach ($pages as $data) {
            $page++;

            if ($data === null) {
                if ($page === 1) {
                    throw SourceUnavailable::because();
                }

                // Площадка перестала отдавать раньше настроенного предела; полноту оценивает уже потребитель.
                Log::channel('scraping')->warning('Reviews stopped being served before the expected limit.', [
                    'source' => $source->toString(),
                    'page' => $page,
                ]);

                return;
            }

            $count = $data->object('params')->int('count');
            $reviews = $this->mapper->reviews($data);

            if ($reviews === []) {
                if ($page === 1 && $count > 0) {
                    throw $data->invalid('reviews', "non-empty on the first page when params.count is {$count}");
                }

                return;
            }

            $expectedTotal = min($count, $this->settings->maxAvailableReviews);

            // Пока приходят новые отзывы, старые сдвигаются между страницами; каждый отдаём ровно один раз.
            $fresh = array_values(array_filter($reviews, static function (SourceReview $review) use (&$seen): bool {
                if (isset($seen[$review->externalId])) {
                    return false;
                }

                return $seen[$review->externalId] = true;
            }));

            yield new ReviewPage($fresh, $page, $expectedTotal);

            // Останавливаемся, не запрашивая страницу, в которой уже ничего не может быть: params.totalPages ненадёжен.
            if ($page * $this->settings->pageSize >= $expectedTotal) {
                return;
            }
        }
    }
}
