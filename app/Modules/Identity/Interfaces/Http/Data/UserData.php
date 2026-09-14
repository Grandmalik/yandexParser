<?php

declare(strict_types=1);

namespace App\Modules\Identity\Interfaces\Http\Data;

use App\Modules\Identity\Application\UserView;
use Spatie\LaravelData\Data;

final class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}

    /**
     * Магический конструктор spatie/laravel-data: срабатывает на `UserData::from($view)`.
     */
    public static function fromUserView(UserView $user): self
    {
        return new self(id: $user->id, name: $user->name, email: $user->email);
    }
}
