<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Source;

use InvalidArgumentException;

/**
 * Адрес карточки организации на площадке, например yandex:1124715036.
 */
final readonly class SourceReference
{
    private const int MAX_EXTERNAL_ID_LENGTH = 128;

    public function __construct(
        public Platform $platform,
        public string $externalId,
    ) {
        if ($externalId === '' || strlen($externalId) > self::MAX_EXTERNAL_ID_LENGTH) {
            throw new InvalidArgumentException('External id must be 1-'.self::MAX_EXTERNAL_ID_LENGTH.' characters long.');
        }
    }

    public function equals(self $other): bool
    {
        return $other->toString() === $this->toString();
    }

    /**
     * Строковый вид «площадка:внешний id» — им ссылка на источник попадает в логи и ответы API.
     */
    public function toString(): string
    {
        return $this->platform->value.':'.$this->externalId;
    }
}
