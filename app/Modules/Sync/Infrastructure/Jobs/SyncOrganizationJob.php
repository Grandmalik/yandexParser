<?php

declare(strict_types=1);

namespace App\Modules\Sync\Infrastructure\Jobs;

use App\Modules\Shared\Application\Context\CorrelationContext;
use App\Modules\Sync\Application\MarkSyncFailed;
use App\Modules\Sync\Application\RunSync;
use App\Modules\Sync\Application\SyncRetry;
use App\Modules\Sync\Domain\SyncRunId;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Собирает данные одной организации вне цикла запроса. Задача уникальна по прогону: пока она в очереди,
 * вторая такая же не поставится.
 */
final class SyncOrganizationJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public function __construct(public readonly string $syncRunId) {}

    /**
     * Ключ уникальности задачи — id прогона.
     */
    public function uniqueId(): string
    {
        return $this->syncRunId;
    }

    /**
     * Сколько попыток разрешено (из конфигурации).
     */
    public function tries(): int
    {
        return config()->integer('sync.max_attempts');
    }

    /**
     * До какого момента вообще имеет смысл повторять.
     */
    public function retryUntil(): DateTimeInterface
    {
        return Date::now()->addMinutes(config()->integer('sync.retry_until_minutes'));
    }

    /**
     * Выполняет прогон. При ошибке решение принимает не конвейер, а задача: есть задержка — вернуть в очередь,
     * нет — признать прогон провалившимся.
     */
    public function handle(RunSync $run, SyncRetry $retry, MarkSyncFailed $markFailed): void
    {
        Context::add(CorrelationContext::SYNC_RUN_ID, $this->syncRunId);
        $id = SyncRunId::fromString($this->syncRunId);

        try {
            $run->handle($id);
        } catch (Throwable $failure) {
            $delay = $retry->delayFor($failure, $this->attempts());

            if ($delay === null) {
                $markFailed->handle($id, $failure);
                $this->fail($failure);

                return;
            }

            $this->release($delay);
        }
    }

    /**
     * Ловит и то, чего сама задача поймать не может: таймаут, перезапуск воркера, исчерпанные попытки.
     * Благодаря этому прогон никогда не зависает в статусе «идёт сбор» навсегда.
     */
    public function failed(Throwable $failure): void
    {
        app(MarkSyncFailed::class)->handle(SyncRunId::fromString($this->syncRunId), $failure);
    }
}
