<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Http;

use Random\Randomizer;
use UnexpectedValueException;

/**
 * Набор профилей браузера из конфигурации; из него берётся один на сессию.
 */
final readonly class BrowserProfiles
{
    /**
     * @param  non-empty-list<BrowserProfile>  $profiles
     */
    public function __construct(
        private array $profiles,
        private Randomizer $random = new Randomizer,
    ) {}

    /**
     * Собирает профили из конфигурации; кривая запись — ошибка конфигурации, а не молчаливый пропуск.
     *
     * @param  array<array-key, mixed>  $config  Список вида ['user_agent' => string, 'headers' => array<string, string>].
     */
    public static function fromConfig(array $config): self
    {
        $profiles = [];

        foreach ($config as $index => $profile) {
            $userAgent = is_array($profile) ? ($profile['user_agent'] ?? null) : null;
            $headers = is_array($profile) ? ($profile['headers'] ?? []) : null;

            if (! is_string($userAgent) || ! is_array($headers)) {
                throw new UnexpectedValueException("scraping.browser_profiles.{$index} must have a user_agent and headers.");
            }

            $profiles[] = new BrowserProfile($userAgent, self::headers($headers, $index));
        }

        if ($profiles === []) {
            throw new UnexpectedValueException('scraping.browser_profiles must not be empty.');
        }

        return new self($profiles);
    }

    /**
     * Проверяет, что заголовки профиля — это пары «строка → строка».
     *
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private static function headers(array $headers, int|string $index): array
    {
        $valid = [];

        foreach ($headers as $name => $value) {
            if (! is_string($name) || ! is_string($value)) {
                throw new UnexpectedValueException("scraping.browser_profiles.{$index}.headers must map header names to strings.");
            }

            $valid[$name] = $value;
        }

        return $valid;
    }

    /**
     * Случайный профиль — он закрепится за сессией целиком.
     */
    public function pick(): BrowserProfile
    {
        return $this->profiles[$this->random->getInt(0, count($this->profiles) - 1)];
    }
}
