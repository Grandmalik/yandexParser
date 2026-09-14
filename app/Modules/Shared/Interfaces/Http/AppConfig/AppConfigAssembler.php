<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Illuminate\Contracts\Container\Container;

/**
 * Собирает ответ `GET /app-config` из секций, которые объявили модули.
 */
final readonly class AppConfigAssembler
{
    public function __construct(private Container $container) {}

    /**
     * Обходит все секции и складывает их в один объект по ключу каждой.
     *
     * @return array<string, array<array-key, mixed>>
     */
    public function assemble(): array
    {
        /** @var iterable<AppConfigSection> $sections */
        $sections = $this->container->tagged(AppConfigSection::TAG);

        $config = [];

        foreach ($sections as $section) {
            $config[$section->key()] = $section->data()->toArray();
        }

        return $config;
    }
}
