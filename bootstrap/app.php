<?php

declare(strict_types=1);

use App\Modules\Shared\Interfaces\Http\Errors\ApiExceptionRenderer;
use App\Modules\Shared\Interfaces\Http\Middleware\AssignTraceId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignTraceId::class);
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => app(ApiExceptionRenderer::class)->shouldRender($request),
        );
        $exceptions->render(
            fn (Throwable $exception, Request $request) => app(ApiExceptionRenderer::class)->render($exception, $request),
        );
    })->create();
