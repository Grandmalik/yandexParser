<?php

declare(strict_types=1);

namespace App\Modules\Shared;

use App\Modules\Shared\Infrastructure\Clock\SystemClock;
use App\Modules\Shared\Interfaces\Http\AppConfig\AppConfigSection;
use App\Modules\Shared\Interfaces\Http\AppConfig\ApplicationConfigSection;
use App\Modules\Shared\Interfaces\Http\AppConfig\BroadcastingConfigSection;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

final class SharedServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        ClockInterface::class => SystemClock::class,
    ];

    public function register(): void
    {
        $this->app->tag([ApplicationConfigSection::class, BroadcastingConfigSection::class], AppConfigSection::TAG);
    }
}
