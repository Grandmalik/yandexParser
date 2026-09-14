<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\Data;

use Spatie\LaravelData\Data;

/**
 * Почему прогон закончился плохо: стабильный код — для логики клиента, переведённое сообщение — для человека.
 */
final class SyncFailureData extends Data
{
    public function __construct(
        public string $code,
        public string $message,
    ) {}
}
