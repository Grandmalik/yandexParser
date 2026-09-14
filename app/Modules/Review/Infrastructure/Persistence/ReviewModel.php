<?php

declare(strict_types=1);

namespace App\Modules\Review\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Запись отзыва для чтения. Пишет только EloquentReviewStore — сразу целыми страницами.
 *
 * @property int $id
 * @property string $organization_id
 * @property string $external_id
 * @property string|null $author_name
 * @property int|null $rating
 * @property string|null $text
 * @property string|null $business_reply
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $removed_at
 */
#[Table('reviews')]
final class ReviewModel extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'published_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
        ];
    }
}
