<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

/**
 * Всё в контракте Яндекс Карт, что может измениться, — читается из конфигурации `scraping.platforms.yandex`.
 */
final readonly class YandexSettings
{
    private const string EXTERNAL_ID_PLACEHOLDER = '{external_id}';

    public function __construct(
        public string $baseUrl,
        public string $organizationPagePath,
        public string $reviewsApiPath,
        public string $locale,
        public string $ranking,
        public int $pageSize,
        public int $maxAvailableReviews,
    ) {}

    public static function fromConfig(): self
    {
        $yandex = 'scraping.platforms.yandex';

        return new self(
            baseUrl: rtrim(config()->string("{$yandex}.http.base_url"), '/'),
            organizationPagePath: config()->string("{$yandex}.http.organization_page_path"),
            reviewsApiPath: config()->string("{$yandex}.http.reviews_api_path"),
            locale: config()->string("{$yandex}.reviews.locale"),
            ranking: config()->string("{$yandex}.reviews.ranking"),
            pageSize: config()->integer("{$yandex}.reviews.page_size"),
            maxAvailableReviews: config()->integer("{$yandex}.reviews.max_available"),
        );
    }

    public function organizationPageUrl(string $externalId): string
    {
        return $this->url($this->organizationPagePath, $externalId);
    }

    public function reviewsApiUrl(): string
    {
        return $this->baseUrl.$this->reviewsApiPath;
    }

    private function url(string $path, string $externalId): string
    {
        return $this->baseUrl.str_replace(self::EXTERNAL_ID_PLACEHOLDER, rawurlencode($externalId), $path);
    }
}
