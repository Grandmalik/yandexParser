<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Domain\Url;

/**
 * Ссылка, вставленная пользователем, приведённая к сравнимому виду: схема добавлена, если её не было,
 * хост — в нижнем регистре и без «www.».
 */
final readonly class SourceUrl
{
    private const int MAX_LENGTH = 2048;

    private const array ALLOWED_SCHEMES = ['http', 'https'];

    /**
     * @param  array<array-key, mixed>  $query
     */
    private function __construct(
        public string $scheme,
        public string $host,
        public string $path,
        public array $query,
    ) {}

    /**
     * Разбирает строку в ссылку; null — если это не пригодная ссылка.
     */
    public static function tryFrom(string $value): ?self
    {
        $value = trim($value);

        if ($value === '' || strlen($value) > self::MAX_LENGTH) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) !== 1) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        // Логин с паролем в ссылке здесь не бывает легитимным: это классический способ замаскировать настоящий хост.
        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! in_array($scheme, self::ALLOWED_SCHEMES, true) || ! str_contains($host, '.')) {
            return null;
        }

        parse_str($parts['query'] ?? '', $query);

        return new self(
            scheme: $scheme,
            host: str_starts_with($host, 'www.') ? substr($host, 4) : $host,
            path: $parts['path'] ?? '/',
            query: $query,
        );
    }

    /**
     * Строковый параметр запроса или null.
     */
    public function queryParameter(string $name): ?string
    {
        $value = $this->query[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Нормализованная ссылка строкой.
     */
    public function toString(): string
    {
        $query = http_build_query($this->query);

        return $this->scheme.'://'.$this->host.$this->path.($query === '' ? '' : '?'.$query);
    }
}
