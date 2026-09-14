<?php

declare(strict_types=1);

namespace App\Modules\Shared\Interfaces\Http\Data;

use App\Modules\Shared\Domain\HasLabel;
use BackedEnum;
use Illuminate\Support\Facades\Lang;
use Spatie\LaravelData\Data;

/**
 * Значение энума вместе с переведённой подписью, чтобы SPA не сопоставлял значения текстам сам.
 */
final class EnumOptionData extends Data
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}

    /**
     * Энум → пара «значение + подпись». Нет перевода — показываем ключ: пропажа строки должна быть заметна.
     */
    public static function fromEnum(BackedEnum&HasLabel $enum): self
    {
        $label = Lang::get($enum->labelKey());

        return new self(value: (string) $enum->value, label: is_string($label) ? $label : $enum->labelKey());
    }
}
