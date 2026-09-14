<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

/**
 * Что запустило сбор.
 */
enum SyncTrigger: string
{
    /** Первый сбор сразу после подключения организации. */
    case Connected = 'connected';
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
