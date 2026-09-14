<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Errors;

use Spatie\LaravelData\Data;

/**
 * Тело любого ответа API с ошибкой, завёрнутое в `{"error": ApiErrorData}`.
 */
final class ApiErrorData extends Data
{
    /**
     * @param  string  $code  Стабильный машиночитаемый код; по нему клиент ветвит логику.
     * @param  string  $message  Переведённое человекочитаемое сообщение; клиент показывает его как есть.
     * @param  array<string, array<string>>|null  $fields  Сообщения валидации по полям формы.
     * @param  array<string, mixed>|null  $details  Дополнительные данные, своя структура у каждого кода.
     */
    public function __construct(
        public string $code,
        public string $message,
        public ?array $fields = null,
        public ?array $details = null,
        public ?string $traceId = null,
    ) {}
}
