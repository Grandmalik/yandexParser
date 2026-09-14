<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Identity;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Stringable;

/**
 * Основа идентификаторов агрегатов. UUIDv7 упорядочен по времени, поэтому индекс первичного ключа
 * не разрастается от случайных вставок.
 */
abstract readonly class UuidIdentifier implements Stringable
{
    final private function __construct(public string $value) {}

    /**
     * Новый идентификатор.
     */
    public static function generate(): static
    {
        return new static(Uuid::uuid7()->toString());
    }

    /**
     * Разбирает строку; неверный UUID — исключение.
     */
    public static function fromString(string $value): static
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a valid %s.', $value, static::class));
        }

        return new static(strtolower($value));
    }

    /**
     * Равенство по типу идентификатора и значению.
     */
    public function equals(self $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
