<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application;

/**
 * Итог одной живой проверки в тех терминах, в которых её читает эксплуатация: прошла или нет, что сообщила
 * площадка, сколько заняла.
 */
final readonly class CanaryResult
{
    private function __construct(
        public string $url,
        public bool $passed,
        public int $durationMs,
        public ?string $source = null,
        public ?string $name = null,
        public int $reviewsReported = 0,
        public int $reviewsAvailable = 0,
        public int $reviewsOnFirstPage = 0,
        public ?string $errorCode = null,
        public ?string $message = null,
    ) {}

    /**
     * Успешная проверка: что за карточка и сколько отзывов площадка отдала.
     */
    public static function passed(
        string $url,
        string $source,
        string $name,
        int $reviewsReported,
        int $reviewsAvailable,
        int $reviewsOnFirstPage,
        int $durationMs,
    ): self {
        return new self(
            url: $url,
            passed: true,
            durationMs: $durationMs,
            source: $source,
            name: $name,
            reviewsReported: $reviewsReported,
            reviewsAvailable: $reviewsAvailable,
            reviewsOnFirstPage: $reviewsOnFirstPage,
        );
    }

    /**
     * Провалившаяся проверка: код ошибки и сообщение для оператора.
     */
    public static function failed(string $url, string $errorCode, string $message, int $durationMs): self
    {
        return new self(
            url: $url,
            passed: false,
            durationMs: $durationMs,
            errorCode: $errorCode,
            message: $message,
        );
    }

    /**
     * Задействовала ли проверка парсер отзывов. Карточка без единого отзыва отвечает, но о парсере не говорит ничего.
     */
    public function readReviews(): bool
    {
        return $this->reviewsOnFirstPage > 0;
    }
}
