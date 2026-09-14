<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Запись агрегата Organization в БД; пишется только через EloquentOrganizationRepository.
 *
 * @property string $id
 * @property string $platform
 * @property string $external_id
 * @property string $source_url
 * @property string|null $name
 * @property string|null $address
 * @property string|null $rating
 * @property int|null $ratings_count
 * @property int|null $reviews_count
 * @property CarbonImmutable|null $metrics_updated_at
 */
#[Table('organizations', keyType: 'string', incrementing: false)]
#[Unguarded]
final class OrganizationModel extends Model
{
    public const string MEMBERS_TABLE = 'organization_user';

    /**
     * Организации, к которым у пользователя есть доступ. Членство читается одинаково везде, где о нём спрашивают.
     *
     * @return Builder<self>
     */
    public static function ofMember(int $userId): Builder
    {
        return self::query()->whereIn(
            'id',
            DB::table(self::MEMBERS_TABLE)->where('user_id', $userId)->select('organization_id'),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'metrics_updated_at' => 'immutable_datetime',
        ];
    }
}
