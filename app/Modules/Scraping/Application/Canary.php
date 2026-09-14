<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application;

use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceUrlResolver;
use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Source\SourceReference;
use Throwable;

/**
 * Читает одну известную карточку от начала до конца, чтобы выяснить, отвечает ли площадка так, как ждёт парсер.
 * По расписанию это превращает тихую смену формата в сигнал раньше, чем пользователь заметит пропажу данных.
 */
final readonly class Canary
{
    public function __construct(
        private SourceUrlResolver $urls,
        private ReviewSourceGateway $source,
    ) {}

    /**
     * Проверяет карточку и возвращает итог. Исключения наружу не выпускает: сбой — это тоже результат проверки.
     */
    public function check(string $url): CanaryResult
    {
        $startedAt = hrtime(true);

        try {
            $resolved = $this->urls->resolve($url);
            $organization = $this->source->fetchOrganization($resolved->reference);
            $page = $this->firstPage($resolved->reference);

            return CanaryResult::passed(
                url: $url,
                source: $resolved->reference->toString(),
                name: $organization->name,
                reviewsReported: $organization->reviewsCount,
                reviewsAvailable: $page === null ? 0 : $page->expectedTotal,
                reviewsOnFirstPage: $page === null ? 0 : count($page->reviews),
                durationMs: $this->msSince($startedAt),
            );
        } catch (Throwable $failure) {
            return CanaryResult::failed(
                url: $url,
                errorCode: $failure instanceof DomainException ? $failure->errorCode->code() : 'internal',
                message: $failure->getMessage(),
                durationMs: $this->msSince($startedAt),
            );
        }
    }

    /**
     * Читается только первая страница: она задействует токен, подпись и схему отзыва, но не тянет сотни
     * записей на каждой проверке.
     */
    private function firstPage(SourceReference $source): ?ReviewPage
    {
        foreach ($this->source->fetchReviews($source) as $page) {
            return $page;
        }

        return null;
    }

    /**
     * Длительность проверки в миллисекундах.
     */
    private function msSince(int|float $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
