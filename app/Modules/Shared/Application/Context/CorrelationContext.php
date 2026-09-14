<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Context;

/**
 * Ключи сквозных данных запроса или задачи (контекст выполнения и логов).
 */
final class CorrelationContext
{
    /** Id прогона сбора, который выполняет задача: ставит модуль Sync, читают нижние слои (например, запись сырых ответов). */
    public const string SYNC_RUN_ID = 'sync_run_id';
}
