<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Domain\Url;

use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Знает форматы ссылок одной площадки.
 */
interface PlatformUrlParser
{
    /**
     * Собирает парсер из блока `url` самой площадки: добавить площадку — это запись в конфиге плюс её адаптер,
     * без правок провайдера.
     *
     * @param  array<array-key, mixed>  $url
     */
    public static function fromConfig(array $url): self;

    /**
     * Площадка, ссылки которой разбирает этот парсер.
     */
    public function platform(): Platform;

    /**
     * Ведёт ли ссылка на эту площадку вообще (по списку разрешённых хостов).
     */
    public function supports(SourceUrl $url): bool;

    /**
     * Короткая ли это ссылка, которую надо раскрыть переходом по редиректу.
     */
    public function isShortLink(SourceUrl $url): bool;

    /**
     * Организация, на которую указывает ссылка; null — если это не карточка организации.
     */
    public function parse(SourceUrl $url): ?SourceReference;

    /**
     * Канонический адрес карточки — именно его мы храним и показываем.
     */
    public function canonicalUrl(SourceReference $reference): string;
}
