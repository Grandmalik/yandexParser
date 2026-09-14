<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * Показатели организации, снятые одним прогоном сбора.
 *
 * @property int $id
 * @property string $organization_id
 * @property string|null $sync_run_id
 * @property string $rating
 * @property int $ratings_count
 * @property int $reviews_count
 * @property CarbonImmutable $captured_at
 */
#[Table('organization_snapshots')]
#[Unguarded]
final class OrganizationSnapshotModel extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'captured_at' => 'immutable_datetime',
        ];
    }
}
