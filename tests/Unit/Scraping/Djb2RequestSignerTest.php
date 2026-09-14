<?php

declare(strict_types=1);

use App\Modules\Scraping\Infrastructure\Yandex\Signing\Djb2RequestSigner;

it('signs requests exactly like the requests the platform accepted', function (array $vector): void {
    expect((new Djb2RequestSigner)->signedQuery($vector['params']))->toBe($vector['query'].'&s='.$vector['s']);
})->with(fn (): array => array_map(fn (array $vector): array => [$vector], recordedJson('Yandex/signature_vectors.json')));

it('does not depend on the order parameters are given in', function (): void {
    $signer = new Djb2RequestSigner;

    expect($signer->signedQuery(['page' => '1', 'ajax' => '1']))->toBe($signer->signedQuery(['ajax' => '1', 'page' => '1']));
});

it('encodes values like encodeURIComponent', function (): void {
    expect((new Djb2RequestSigner)->signedQuery(['text' => "a b:c!*'()"]))->toStartWith("text=a%20b%3Ac!*'()&s=");
});
