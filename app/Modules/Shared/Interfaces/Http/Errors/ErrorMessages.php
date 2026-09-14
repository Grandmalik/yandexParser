<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Errors;

use Illuminate\Contracts\Translation\Translator;

/**
 * Переводит ключи сообщений об ошибках. Если строки нет, возвращает сам ключ: пропажа перевода должна быть
 * видна, а не превращаться в пустоту на экране.
 */
final readonly class ErrorMessages
{
    public function __construct(private Translator $translator) {}

    /**
     * @param  array<string, scalar>  $parameters
     */
    public function translate(string $key, array $parameters = []): string
    {
        $message = $this->translator->get($key, $parameters);

        return is_string($message) ? $message : $key;
    }
}
