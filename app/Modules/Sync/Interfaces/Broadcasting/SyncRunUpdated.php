<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Broadcasting;

use App\Modules\Organization\Application\Contracts\OrganizationChannel;
use App\Modules\Sync\Interfaces\Http\Data\SyncRunData;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Прогресс сбора, который уходит участникам организации. Внутри — ровно тот же SyncRunData, что отдают
 * REST-эндпоинты, поэтому экран показывает пришедшее пушем и запрошенное опросом одним и тем же кодом.
 *
 * Рассылается немедленно, а не через очередь: сообщение крошечное, полезно только пока сбор идёт, а постановка
 * в очередь означала бы, что прогресс молча замирает, когда очередь по умолчанию никто не слушает, — воркер
 * этого приложения слушает очередь сбора.
 */
final readonly class SyncRunUpdated implements ShouldBroadcastNow
{
    public const string NAME = 'sync-run.updated';

    public function __construct(private SyncRunData $run) {}

    /**
     * Приватный канал организации, которой принадлежит прогон.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel(OrganizationChannel::for($this->run->organizationId));
    }

    /**
     * Имя события, на которое подписывается браузер.
     */
    public function broadcastAs(): string
    {
        return self::NAME;
    }

    /**
     * Полезная нагрузка события.
     *
     * @return array{sync_run: array<array-key, mixed>}
     */
    public function broadcastWith(): array
    {
        return ['sync_run' => $this->run->toArray()];
    }
}
