<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts\Exceptions;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\ScrapingErrorCode;
use App\Modules\Shared\Domain\Error\DomainException;

/**
 * Ответ площадки не совпал с ожидаемым форматом. Повторов не будет: это чинится правкой парсера, а не попыткой.
 */
final class SourceDrift extends DomainException
{
    /**
     * @param  string  $payload  Сырой ответ, сохраняется для разбора; клиенту API не отдаётся никогда.
     */
    private function __construct(
        public readonly DriftStage $stage,
        public readonly string $expectation,
        public readonly string $payload,
        public readonly ?int $httpStatus,
    ) {
        parent::__construct(ScrapingErrorCode::Drift, details: ['stage' => $stage->value, 'expectation' => $expectation]);
    }

    /**
     * Фиксирует расхождение: на какой стадии, чего именно ждали и что пришло вместо этого.
     */
    public static function at(DriftStage $stage, string $expectation, string $payload, ?int $httpStatus = null): self
    {
        return new self($stage, $expectation, $payload, $httpStatus);
    }
}
