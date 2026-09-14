<?php

declare(strict_types=1);

namespace App\Modules\Identity\Interfaces\Http\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Data;

/**
 * Тело запроса `POST /auth/login`; валидируется при внедрении в контроллер.
 */
final class LoginData extends Data
{
    public function __construct(
        #[Email, Max(255)]
        public string $email,
        #[Max(255)]
        public string $password,
        public bool $remember = false,
    ) {}
}
