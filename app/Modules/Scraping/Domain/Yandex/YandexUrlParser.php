<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Domain\Yandex;

use App\Modules\Scraping\Domain\Url\PlatformUrlParser;
use App\Modules\Scraping\Domain\Url\SourceUrl;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Разбирает ссылки на карточки Яндекс Карт. Поддерживаются:
 *  - /maps/org/{slug}/{id}/[reviews/…], /maps/org/{id}/, /maps/{регион}/{город}/org/{slug}/{id}/
 *  - /profile/{id}
 *  - любая ссылка /maps/… с параметром `oid` (организация, открытая на карте)
 *  - короткие ссылки /maps/-/{код} — их раскрывает вызывающий код
 */
final readonly class YandexUrlParser implements PlatformUrlParser
{
    private const string CARD_PATH_PATTERN = '#/(?:org|profile)/(?:[^/]+/)?(\d{5,20})(?:/|$)#';

    private const string ORGANIZATION_ID_PATTERN = '/^\d{5,20}$/';

    private const string ORGANIZATION_QUERY_PARAMETER = 'oid';

    private const string EXTERNAL_ID_PLACEHOLDER = '{external_id}';

    /**
     * @param  list<string>  $hosts  Разрешённые хосты без «www.».
     */
    public function __construct(
        private array $hosts,
        private string $shortLinkPathPrefix,
        private string $canonicalUrlTemplate,
    ) {}

    public static function fromConfig(array $url): self
    {
        return new self(
            hosts: array_values(array_filter(is_array($url['hosts'] ?? null) ? $url['hosts'] : [], is_string(...))),
            shortLinkPathPrefix: is_string($url['short_link_path_prefix'] ?? null) ? $url['short_link_path_prefix'] : '',
            canonicalUrlTemplate: is_string($url['canonical_url'] ?? null) ? $url['canonical_url'] : '',
        );
    }

    public function platform(): Platform
    {
        return Platform::Yandex;
    }

    public function supports(SourceUrl $url): bool
    {
        return in_array($url->host, $this->hosts, true);
    }

    public function isShortLink(SourceUrl $url): bool
    {
        return str_starts_with($url->path, $this->shortLinkPathPrefix);
    }

    public function parse(SourceUrl $url): ?SourceReference
    {
        if (preg_match(self::CARD_PATH_PATTERN, $url->path, $matches) === 1) {
            return new SourceReference(Platform::Yandex, $matches[1]);
        }

        $organizationId = $url->queryParameter(self::ORGANIZATION_QUERY_PARAMETER);

        if ($organizationId !== null && preg_match(self::ORGANIZATION_ID_PATTERN, $organizationId) === 1) {
            return new SourceReference(Platform::Yandex, $organizationId);
        }

        return null;
    }

    public function canonicalUrl(SourceReference $reference): string
    {
        return str_replace(self::EXTERNAL_ID_PLACEHOLDER, $reference->externalId, $this->canonicalUrlTemplate);
    }
}
