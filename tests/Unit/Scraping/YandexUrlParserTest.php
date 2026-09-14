<?php

declare(strict_types=1);

use App\Modules\Scraping\Domain\Url\SourceUrl;
use App\Modules\Scraping\Domain\Yandex\YandexUrlParser;
use App\Modules\Shared\Domain\Source\Platform;

beforeEach(function (): void {
    $this->parser = new YandexUrlParser(
        hosts: ['yandex.ru', 'yandex.com', 'yandex.kz'],
        shortLinkPathPrefix: '/maps/-/',
        canonicalUrlTemplate: 'https://yandex.ru/maps/org/{external_id}/',
    );
});

function yandexUrl(string $url): SourceUrl
{
    return SourceUrl::tryFrom($url) ?? throw new LogicException("Fixture URL {$url} is not a URL.");
}

it('recognizes organization card links', function (string $url): void {
    $reference = $this->parser->parse(yandexUrl($url));

    expect($reference?->platform)->toBe(Platform::Yandex)
        ->and($reference?->externalId)->toBe('1234567890');
})->with([
    'card with slug' => 'https://yandex.ru/maps/org/kofeynya_tsentr/1234567890/',
    'reviews tab' => 'https://yandex.ru/maps/org/kofeynya_tsentr/1234567890/reviews/',
    'card without slug and scheme' => 'yandex.ru/maps/org/1234567890',
    'www and query string' => 'https://www.yandex.com/maps/org/cafe/1234567890/?ll=37.6%2C55.7&z=16',
    'card inside a city' => 'https://yandex.kz/maps/162/almaty/org/cafe/1234567890/',
    'organization opened on the map' => 'https://yandex.ru/maps/213/moscow/?ll=37.62%2C55.75&mode=poi&oid=1234567890&ol=biz',
    'business profile' => 'https://yandex.ru/profile/1234567890',
    'upper-case host' => 'HTTPS://YANDEX.RU/maps/org/cafe/1234567890/',
]);

it('does not mistake other Yandex Maps pages for a card', function (string $url): void {
    expect($this->parser->parse(yandexUrl($url)))->toBeNull();
})->with([
    'city map' => 'https://yandex.ru/maps/213/moscow/',
    'search results' => 'https://yandex.ru/maps/?text=%D0%BA%D0%BE%D1%84%D0%B5',
    'non-numeric id' => 'https://yandex.ru/maps/org/cafe/abcdef/',
    'too short id' => 'https://yandex.ru/maps/org/cafe/123/',
    'non-numeric oid' => 'https://yandex.ru/maps/?oid=abc',
]);

it('accepts only allowlisted hosts', function (string $url, bool $supported): void {
    expect($this->parser->supports(yandexUrl($url)))->toBe($supported);
})->with([
    'yandex.ru' => ['https://yandex.ru/maps/org/cafe/1234567890/', true],
    'another platform' => ['https://google.com/maps/org/cafe/1234567890/', false],
    'look-alike subdomain' => ['https://yandex.ru.evil.example/maps/org/cafe/1234567890/', false],
    'look-alike domain' => ['https://evilyandex.ru/maps/org/cafe/1234567890/', false],
]);

it('detects short links', function (): void {
    expect($this->parser->isShortLink(yandexUrl('https://yandex.ru/maps/-/CCUqYHh0dB')))->toBeTrue()
        ->and($this->parser->isShortLink(yandexUrl('https://yandex.ru/maps/org/cafe/1234567890/')))->toBeFalse();
});

it('builds the canonical card URL from the external id', function (): void {
    $reference = $this->parser->parse(yandexUrl('https://yandex.ru/maps/org/cafe/1234567890/reviews/?utm_source=share'));

    expect($this->parser->canonicalUrl($reference))->toBe('https://yandex.ru/maps/org/1234567890/');
});
