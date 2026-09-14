<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Errors;

use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Error\ErrorKind;
use App\Modules\Shared\Interfaces\Http\Middleware\AssignTraceId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Превращает любое исключение, возникшее при обслуживании API, в единый конверт `{"error": ApiErrorData}`.
 */
final readonly class ApiExceptionRenderer
{
    public function __construct(private Translator $translator) {}

    /**
     * Отвечать ли JSON-ошибкой: это запрос к API или клиент явно просит JSON.
     */
    public function shouldRender(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Подбирает код, статус и сообщение по типу исключения; null — значит ответ отдаёт не этот рендерер.
     */
    public function render(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $this->shouldRender($request) || $exception instanceof HttpResponseException) {
            return null;
        }

        return match (true) {
            $exception instanceof DomainException => $this->respond(
                code: $exception->errorCode->code(),
                message: $this->translate($exception->errorCode->messageKey(), $exception->messageParameters),
                status: $this->statusOf($exception->errorCode->kind()),
                details: $exception->details ?: null,
            ),
            $exception instanceof ValidationException => $this->respondWith(
                ApiErrorCode::ValidationFailed,
                status: $exception->status,
                fields: $exception->validator->errors()->messages(),
            ),
            $exception instanceof AuthenticationException => $this->respondWith(ApiErrorCode::Unauthenticated, status: 401),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception),
            default => $this->respondWith(
                ApiErrorCode::Internal,
                status: 500,
                details: config()->boolean('app.debug') ? $this->debugDetails($exception) : null,
            ),
        };
    }

    /**
     * HTTP-исключения фреймворка: статус, его заголовки и `retry_after` для 429.
     */
    private function fromHttpException(HttpExceptionInterface $exception): JsonResponse
    {
        $status = $exception->getStatusCode();
        $headers = $exception->getHeaders();
        $code = ApiErrorCode::fromStatus($status);
        $retryAfter = $headers['Retry-After'] ?? null;

        return $this->respondWith(
            $code,
            status: $status,
            details: $code === ApiErrorCode::RateLimited && is_numeric($retryAfter) ? ['retry_after' => (int) $retryAfter] : null,
            headers: $headers,
            messageParameters: ['seconds' => is_numeric($retryAfter) ? (int) $retryAfter : ''],
        );
    }

    /**
     * Ответ по коду транспортной ошибки: сообщение берётся из переводов по этому коду.
     *
     * @param  array<string, array<string>>|null  $fields
     * @param  array<string, mixed>|null  $details
     * @param  array<array-key, mixed>  $headers
     * @param  array<string, scalar>  $messageParameters
     */
    private function respondWith(
        ApiErrorCode $code,
        int $status,
        ?array $fields = null,
        ?array $details = null,
        array $headers = [],
        array $messageParameters = [],
    ): JsonResponse {
        return $this->respond(
            code: $code->value,
            message: $this->translate($code->messageKey(), $messageParameters),
            status: $status,
            fields: $fields,
            details: $details,
            headers: $headers,
        );
    }

    /**
     * Собирает конверт ошибки и добавляет в него trace_id текущего запроса.
     *
     * @param  array<string, array<string>>|null  $fields
     * @param  array<string, mixed>|null  $details
     * @param  array<array-key, mixed>  $headers
     */
    private function respond(
        string $code,
        string $message,
        int $status,
        ?array $fields = null,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        $traceId = Context::get(AssignTraceId::CONTEXT_KEY);

        $error = new ApiErrorData(
            code: $code,
            message: $message,
            fields: $fields,
            details: $details,
            traceId: is_string($traceId) ? $traceId : null,
        );

        return new JsonResponse(['error' => $error->toArray()], $status, $headers);
    }

    /**
     * Категория доменной ошибки → HTTP-статус. Единственное место, где задано это соответствие.
     */
    private function statusOf(ErrorKind $kind): int
    {
        return match ($kind) {
            ErrorKind::Validation => 422,
            ErrorKind::Unauthenticated => 401,
            ErrorKind::Forbidden => 403,
            ErrorKind::NotFound => 404,
            ErrorKind::Conflict => 409,
            ErrorKind::RateLimited => 429,
            ErrorKind::Unavailable => 503,
            ErrorKind::Internal => 500,
        };
    }

    /**
     * Перевод сообщения; строки нет — возвращаем сам ключ, чтобы пропажа была видна.
     *
     * @param  array<string, scalar>  $parameters
     */
    private function translate(string $key, array $parameters): string
    {
        $message = $this->translator->get($key, $parameters);

        return is_string($message) ? $message : $key;
    }

    /**
     * Подробности исключения — только при включённом APP_DEBUG, наружу в продакшене они не уходят.
     *
     * @return array<string, mixed>
     */
    private function debugDetails(Throwable $exception): array
    {
        return [
            'debug' => [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'location' => $exception->getFile().':'.$exception->getLine(),
            ],
        ];
    }
}
