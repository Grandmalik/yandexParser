<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use JsonException;

/**
 * Разбирает, что именно ответил API `fetchReviews`. Ошибки этого API приходят с HTTP 200, поэтому решает тело
 * ответа, а не статус (ai/research/yandex-source.md §5).
 */
final class ReviewsResponseClassifier
{
    public const string TOKEN_FIELD = 'csrfToken';

    private const string DATA_FIELD = 'data';

    private const string ERROR_FIELD = 'error';

    /** `error.code`, которым API отвечает на страницы за пределом выдачи. */
    private const int NOT_SERVED_ERROR_CODE = 500;

    /** `error.code` запроса, который API считает некорректным — например, появился обязательный параметр. */
    private const int INVALID_REQUEST_ERROR_CODE = 400;

    /**
     * Разбирает тело ответа; не-JSON или не объект — это смена формата, а не пустой результат.
     *
     * @return array<array-key, mixed>
     *
     * @throws SourceDrift
     */
    public function decode(string $raw, int $status): array
    {
        try {
            $body = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw SourceDrift::at(DriftStage::ReviewsPage, 'the API must answer with JSON', $raw, $status);
        }

        return is_array($body)
            ? $body
            : throw SourceDrift::at(DriftStage::ReviewsPage, 'the API must answer with a JSON object', $raw, $status);
    }

    /**
     * API выдал новый токен вместо данных — значит, токен сессии протух.
     *
     * @param  array<array-key, mixed>  $body
     */
    public function isTokenRenewal(array $body): bool
    {
        return ! isset($body[self::DATA_FIELD]) && isset($body[self::TOKEN_FIELD]);
    }

    /**
     * Узел `data` страницы или null, если API эту страницу не отдаёт (вышли за предел выдачи). Всё остальное —
     * либо известная ошибка запроса, либо drift.
     *
     * @param  array<array-key, mixed>  $body
     *
     * @throws SourceDrift
     */
    public function data(array $body, string $raw): ?PayloadReader
    {
        $reader = new PayloadReader($body, DriftStage::ReviewsPage, $raw);

        if (isset($body[self::DATA_FIELD])) {
            return $reader->object(self::DATA_FIELD);
        }

        if (isset($body[self::ERROR_FIELD])) {
            $error = $reader->object(self::ERROR_FIELD);

            return match ($error->int('code')) {
                self::NOT_SERVED_ERROR_CODE => null,
                self::INVALID_REQUEST_ERROR_CODE => throw SourceDrift::at(
                    DriftStage::ReviewsRequest,
                    'the request parameters must be accepted: '.($error->optionalString('message') ?? 'no message'),
                    $raw,
                ),
                default => throw $error->invalid('code', 'a known API error code'),
            };
        }

        throw SourceDrift::at(DriftStage::ReviewsPage, 'the response must contain data, error or csrfToken', $raw);
    }
}
