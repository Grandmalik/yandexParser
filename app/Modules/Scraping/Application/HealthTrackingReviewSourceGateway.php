<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application;

use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Scraping\Application\Health\SourceHealth;
use App\Modules\Shared\Domain\Source\SourceReference;
use Generator;
use Throwable;

/**
 * Следит, как площадка нам отвечает, не меняя поведения адаптеров: каждое чтение — одна точка данных о здоровье.
 */
final readonly class HealthTrackingReviewSourceGateway implements ReviewSourceGateway
{
    public function __construct(
        private ReviewSourceGateway $inner,
        private SourceHealth $health,
    ) {}

    /**
     * Читает карточку и записывает исход в окно здоровья.
     */
    public function fetchOrganization(SourceReference $source): SourceOrganization
    {
        try {
            $organization = $this->inner->fetchOrganization($source);
        } catch (Throwable $failure) {
            $this->health->recordFailure($source->platform, $failure);

            throw $failure;
        }

        $this->health->recordSuccess($source->platform);

        return $organization;
    }

    /**
     * По точке данных на каждую страницу: поток, оборвавшийся посередине, говорит о площадке не меньше, чем
     * упавший сразу. Наблюдаем только за получением — в падении того, кто страницы потребляет, площадка не виновата.
     *
     * @return Generator<int, ReviewPage>
     */
    public function fetchReviews(SourceReference $source, int $fromPage = 1): Generator
    {
        $pages = $this->stream($this->inner->fetchReviews($source, $fromPage));

        while ($this->advance($source, static fn (): bool => $pages->valid())) {
            $page = $pages->current();
            $this->health->recordSuccess($source->platform);

            yield $page;

            $this->advance($source, static function () use ($pages): bool {
                $pages->next();

                return true;
            });
        }
    }

    /**
     * Делает один шаг по потоку площадки, записывая сбой до того, как пропустить его дальше.
     *
     * @param  callable(): bool  $step
     */
    private function advance(SourceReference $source, callable $step): bool
    {
        try {
            return $step();
        } catch (Throwable $failure) {
            $this->health->recordFailure($source->platform, $failure);

            throw $failure;
        }
    }

    /**
     * Страницы обёрнутого шлюза как генератор: они остаются ленивыми и сохраняют тип, что бы ни вернул адаптер.
     *
     * @param  iterable<int, ReviewPage>  $pages
     * @return Generator<int, ReviewPage>
     */
    private function stream(iterable $pages): Generator
    {
        yield from $pages;
    }
}
