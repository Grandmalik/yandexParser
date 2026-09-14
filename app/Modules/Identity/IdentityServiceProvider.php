<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Application\Port\Authenticator;
use App\Modules\Identity\Infrastructure\Auth\SessionAuthenticator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

final class IdentityServiceProvider extends ServiceProvider
{
    public const string LOGIN_RATE_LIMITER = 'identity.login';

    /**
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        Authenticator::class => SessionAuthenticator::class,
    ];

    public function boot(): void
    {
        // Лимит попыток входа считается по паре «email + IP»: перебор одного аккаунта не мешает войти остальным.
        RateLimiter::for(self::LOGIN_RATE_LIMITER, static fn (Request $request): Limit => new Limit(
            key: Str::lower($request->string('email')->toString()).'|'.$request->ip(),
            maxAttempts: config()->integer('identity.login.max_attempts'),
            decaySeconds: config()->integer('identity.login.decay_seconds'),
        ));
    }
}
