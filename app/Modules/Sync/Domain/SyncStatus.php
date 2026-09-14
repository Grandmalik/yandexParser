<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

use App\Modules\Shared\Domain\HasLabel;

/**
 * Состояние прогона сбора.
 */
enum SyncStatus: string implements HasLabel
{
    case Queued = 'queued';
    case Running = 'running';
    /** Собрано всё, что площадка отдаёт. */
    case Completed = 'completed';
    /** Данные сохранены, но отзывов собрано меньше ожидаемого. */
    case Partial = 'partial';
    case Failed = 'failed';

    /**
     * Завершён ли прогон — то есть больше он не изменится.
     */
    public function isFinished(): bool
    {
        return match ($this) {
            self::Queued, self::Running => false,
            self::Completed, self::Partial, self::Failed => true,
        };
    }

    public function labelKey(): string
    {
        return 'sync.enums.status.'.$this->value;
    }
}
