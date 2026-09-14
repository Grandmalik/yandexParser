<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\SourceUrl;

use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Scraping\Application\Contracts\ResolvedSource;
use App\Modules\Scraping\Application\Contracts\SourceUrlResolver;
use App\Modules\Scraping\Application\Port\ShortLinkExpander;
use App\Modules\Scraping\Domain\Url\PlatformUrlParser;
use App\Modules\Scraping\Domain\Url\SourceUrl;

/**
 * Превращает ссылку пользователя в адрес карточки: подбирает парсер площадки и раскрывает короткие ссылки.
 */
final readonly class PlatformSourceUrlResolver implements SourceUrlResolver
{
    /**
     * @param  list<PlatformUrlParser>  $parsers
     */
    public function __construct(
        private array $parsers,
        private ShortLinkExpander $shortLinks,
        private int $maxShortLinkHops,
    ) {}

    public function resolve(string $url): ResolvedSource
    {
        $sourceUrl = SourceUrl::tryFrom($url) ?? throw InvalidSourceUrl::malformed();
        $parser = $this->parserFor($sourceUrl) ?? throw InvalidSourceUrl::unsupportedHost($sourceUrl->host);

        // Запрашиваем только ссылки на собственных хостах площадки: каждый переход сверяется со списком разрешённых.
        for ($hops = 0; $parser->isShortLink($sourceUrl); $hops++) {
            if ($hops === $this->maxShortLinkHops) {
                throw InvalidSourceUrl::notAnOrganization();
            }

            $sourceUrl = $this->shortLinks->expand($sourceUrl) ?? throw InvalidSourceUrl::notAnOrganization();

            if (! $parser->supports($sourceUrl)) {
                throw InvalidSourceUrl::unsupportedHost($sourceUrl->host);
            }
        }

        $reference = $parser->parse($sourceUrl) ?? throw InvalidSourceUrl::notAnOrganization();

        return new ResolvedSource($reference, $parser->canonicalUrl($reference));
    }

    /**
     * Первый парсер, который принимает эту ссылку.
     */
    private function parserFor(SourceUrl $url): ?PlatformUrlParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($url)) {
                return $parser;
            }
        }

        return null;
    }
}
