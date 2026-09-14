<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex\Signing;

/**
 * Порт подписи веб-клиента Яндекс Карт (ADR-001, D3): djb2 с XOR по строке запроса, параметры которой
 * отсортированы по имени и закодированы как в JavaScript-функции encodeURIComponent; беззнаковый 32-битный
 * результат уходит в параметр `s`. Поведение сверено с запросами, которые площадка приняла:
 * tests/Fixtures/Yandex/signature_vectors.json.
 */
final class Djb2RequestSigner implements RequestSigner
{
    private const string SIGNATURE_PARAMETER = 's';

    private const int SEED = 5381;

    private const int MULTIPLIER = 33;

    private const int UINT32_MASK = 0xFFFFFFFF;

    /**
     * Символы, которые encodeURIComponent оставляет как есть, в отличие от rawurlencode.
     */
    private const array ENCODE_URI_COMPONENT_EXCEPTIONS = ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')'];

    public function signedQuery(array $parameters): string
    {
        ksort($parameters, SORT_STRING);

        $pairs = [];

        foreach ($parameters as $name => $value) {
            $pairs[] = $name.'='.strtr(rawurlencode($value), self::ENCODE_URI_COMPONENT_EXCEPTIONS);
        }

        $query = implode('&', $pairs);

        return $query.'&'.self::SIGNATURE_PARAMETER.'='.$this->hash($query);
    }

    /**
     * Собственно djb2-XOR по строке запроса.
     */
    private function hash(string $query): int
    {
        $hash = self::SEED;

        for ($i = 0, $length = strlen($query); $i < $length; $i++) {
            $hash = ((self::MULTIPLIER * $hash) & self::UINT32_MASK) ^ ord($query[$i]);
        }

        return $hash;
    }
}
