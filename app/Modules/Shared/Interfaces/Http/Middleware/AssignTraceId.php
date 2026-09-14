<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Связывает запрос с его логами, задачами очереди и ответами об ошибках. Корректный входящий идентификатор
 * (например, от обратного прокси) сохраняется, иначе выдаётся новый.
 */
final class AssignTraceId
{
    public const string CONTEXT_KEY = 'trace_id';

    public const string HEADER = 'X-Request-Id';

    private const string VALID_ID_PATTERN = '/^[A-Za-z0-9._-]{8,128}$/';

    /**
     * Кладёт trace_id в контекст логов и возвращает его же в заголовке ответа.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get(self::HEADER);

        $traceId = $incoming !== null && preg_match(self::VALID_ID_PATTERN, $incoming) === 1
            ? $incoming
            : (string) Str::uuid7();

        Context::add(self::CONTEXT_KEY, $traceId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $traceId);

        return $response;
    }
}
