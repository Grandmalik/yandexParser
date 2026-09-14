<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

/**
 * Маршрут выхода к площадке: прокси-сервер либо собственный IP сервера («direct»).
 */
final readonly class Proxy
{
    public const string DIRECT_ID = 'direct';

    /**
     * @param  string  $id  Его не стыдно писать в лог и в ключи кэша: учётных данных он не содержит.
     */
    private function __construct(
        public string $id,
        public ?string $url,
    ) {}

    /**
     * Выход с собственного IP сервера.
     */
    public static function direct(): self
    {
        return new self(self::DIRECT_ID, null);
    }

    /**
     * Маршрут через прокси; идентификатор — хеш, чтобы пароль не утёк в логи.
     */
    public static function fromUrl(string $url): self
    {
        return new self('proxy-'.hash('xxh64', $url), $url);
    }
}
