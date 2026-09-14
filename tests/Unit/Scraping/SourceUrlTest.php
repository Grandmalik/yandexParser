<?php

declare(strict_types=1);

use App\Modules\Scraping\Domain\Url\SourceUrl;

it('normalizes a pasted link', function (): void {
    $url = SourceUrl::tryFrom('  WWW.Yandex.RU/maps/org/cafe/1234567890/?oid=1  ');

    expect($url?->scheme)->toBe('https')
        ->and($url?->host)->toBe('yandex.ru')
        ->and($url?->path)->toBe('/maps/org/cafe/1234567890/')
        ->and($url?->queryParameter('oid'))->toBe('1')
        ->and($url?->toString())->toBe('https://yandex.ru/maps/org/cafe/1234567890/?oid=1');
});

it('rejects values that are not safe web links', function (string $value): void {
    expect(SourceUrl::tryFrom($value))->toBeNull();
})->with([
    'empty' => '   ',
    'plain words' => 'кофейня на тверской',
    'javascript scheme' => 'javascript://yandex.ru/%0Aalert(1)',
    'ftp scheme' => 'ftp://yandex.ru/maps/org/1234567890',
    'credentials disguising the host' => 'https://yandex.ru@evil.example/maps/org/1234567890',
    'too long' => 'https://yandex.ru/'.str_repeat('a', 2048),
]);
