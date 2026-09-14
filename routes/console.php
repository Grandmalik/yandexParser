<?php

declare(strict_types=1);

use App\Modules\Scraping\Infrastructure\Persistence\SourcePayloadModel;
use App\Modules\Scraping\Interfaces\Console\CanaryCommand;
use App\Modules\Sync\Interfaces\Console\ResyncDueOrganizationsCommand;
use Illuminate\Support\Facades\Schedule;

/*
| Работа по расписанию. Всё здесь выполняется только на одном сервере: второй планировщик удвоил бы нагрузку
| на площадку — ровно то, ради предотвращения чего существует анти-бан.
*/

// Часто и маленькими партиями, а не всё разом; размер партии задаётся в config/sync.php.
Schedule::command(ResyncDueOrganizationsCommand::class)
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Замечает смену формата на известной нам карточке, даже если сегодня никто не запускал сбор.
Schedule::command(CanaryCommand::class)
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Сырые ответы хранятся ровно столько, сколько нужно для разбора поломки (scraping.payloads.retention_days).
Schedule::command('model:prune', ['--model' => [SourcePayloadModel::class]])
    ->daily()
    ->onOneServer();
