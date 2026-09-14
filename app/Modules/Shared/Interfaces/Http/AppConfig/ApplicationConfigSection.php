<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\AppConfig;

use Illuminate\Contracts\Foundation\Application;

/**
 * Секция `application` ответа `/app-config`.
 */
final readonly class ApplicationConfigSection implements AppConfigSection
{
    public function __construct(private Application $app) {}

    public function key(): string
    {
        return 'application';
    }

    public function data(): ApplicationConfigData
    {
        return new ApplicationConfigData(
            name: config()->string('app.name'),
            locale: $this->app->getLocale(),
        );
    }
}
