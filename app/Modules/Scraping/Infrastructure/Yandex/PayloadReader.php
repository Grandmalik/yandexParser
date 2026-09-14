<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Infrastructure\Yandex;

use App\Modules\Scraping\Application\Contracts\DriftStage;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;

/**
 * Типизированный доступ к разобранному ответу площадки с проверкой схемы: любое отсутствующее поле или поле
 * неожиданного типа превращается в SourceDrift с точным путём, а не становится тихо null или мусором.
 */
final readonly class PayloadReader
{
    /**
     * @param  array<array-key, mixed>  $data
     * @param  string  $raw  Весь сырой ответ целиком — он прикладывается к сообщению о drift.
     */
    public function __construct(
        private array $data,
        private DriftStage $stage,
        private string $raw,
        private string $path = '',
    ) {}

    /**
     * Обязательная строка.
     */
    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return is_string($value) ? $value : throw $this->drift($key, 'a string');
    }

    /**
     * Строка, которой может не быть.
     */
    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return $value === null || is_string($value) ? $value : throw $this->drift($key, 'a string or absent');
    }

    /**
     * Целое число.
     */
    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) ? $value : throw $this->drift($key, 'an integer');
    }

    /**
     * Число: площадка отдаёт рейтинг то целым, то дробным.
     */
    public function number(string $key): float
    {
        $value = $this->data[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : throw $this->drift($key, 'a number');
    }

    /**
     * Вложенный объект — читается тем же ридером, с накоплением пути.
     */
    public function object(string $key): self
    {
        return $this->optionalObject($key) ?? throw $this->drift($key, 'an object');
    }

    /**
     * Вложенный объект, которого может не быть.
     */
    public function optionalObject(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->drift($key, 'an object or absent');
        }

        return new self($value, $this->stage, $this->raw, $this->pathOf($key));
    }

    /**
     * Список вложенных объектов.
     *
     * @return list<self>
     */
    public function objects(string $key): array
    {
        $value = $this->data[$key] ?? null;

        if (! is_array($value) || ! array_is_list($value)) {
            throw $this->drift($key, 'a list');
        }

        $items = [];

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                throw $this->drift("{$key}.{$index}", 'an object');
            }

            $items[] = new self($item, $this->stage, $this->raw, $this->pathOf("{$key}.{$index}"));
        }

        return $items;
    }

    /**
     * Сообщает о значении, которое есть и нужного типа, но нарушает смысловое ограничение — например, диапазон.
     */
    public function invalid(string $key, string $expectation): SourceDrift
    {
        return $this->drift($key, $expectation);
    }

    /**
     * Собирает drift с точным путём до поля и тем, чего от него ждали.
     */
    private function drift(string $key, string $expectation): SourceDrift
    {
        return SourceDrift::at($this->stage, "{$this->pathOf($key)} must be {$expectation}", $this->raw);
    }

    /**
     * Полный путь до поля — по нему в сообщении об ошибке видно, где именно разошлась схема.
     */
    private function pathOf(string $key): string
    {
        return $this->path === '' ? $key : "{$this->path}.{$key}";
    }
}
