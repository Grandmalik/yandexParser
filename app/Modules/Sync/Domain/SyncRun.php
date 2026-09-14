<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

use App\Modules\Shared\Domain\Error\ErrorCode;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Жизненный цикл одного сбора данных:
 *
 *   queued ──start──▶ running ──complete──▶ completed
 *      │               │ ▲  └──completePartially──▶ partial
 *      │               └─┘ start (повтор после сорвавшейся попытки)
 *      └──────fail──────┴──────────▶ failed
 */
final class SyncRun
{
    private function __construct(
        public readonly SyncRunId $id,
        public readonly string $organizationId,
        public readonly SyncTrigger $trigger,
        public readonly DateTimeImmutable $requestedAt,
        private SyncStatus $status,
        private int $progressCurrent,
        private ?int $progressTotal,
        private int $nextPage,
        private int $attempts,
        private ?SyncError $error,
        private ?SyncStats $stats,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt,
    ) {}

    /**
     * Новый прогон в очереди: прогресс нулевой, сбор начнётся с первой страницы.
     */
    public static function queue(SyncRunId $id, string $organizationId, SyncTrigger $trigger, DateTimeImmutable $now): self
    {
        return new self($id, $organizationId, $trigger, $now, SyncStatus::Queued, 0, null, 1, 0, null, null, null, null);
    }

    /**
     * Восстанавливает прогон из хранилища.
     */
    public static function restore(
        SyncRunId $id,
        string $organizationId,
        SyncTrigger $trigger,
        DateTimeImmutable $requestedAt,
        SyncStatus $status,
        int $progressCurrent,
        ?int $progressTotal,
        int $nextPage,
        int $attempts,
        ?SyncError $error,
        ?SyncStats $stats,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
    ): self {
        return new self($id, $organizationId, $trigger, $requestedAt, $status, $progressCurrent, $progressTotal, $nextPage, $attempts, $error, $stats, $startedAt, $finishedAt);
    }

    /**
     * Начинает попытку сбора. Время старта ставится только у первой — повтор его не перезаписывает.
     */
    public function start(DateTimeImmutable $now): void
    {
        $this->assertStatus('start', SyncStatus::Queued, SyncStatus::Running);

        $this->status = SyncStatus::Running;
        $this->attempts++;
        $this->startedAt ??= $now;
    }

    /**
     * Двигает прогресс после сохранённой страницы.
     *
     * @param  int  $nextPage  Страница, которую надо запросить следующей: повтор продолжит отсюда, а не начнёт
     *                         сбор заново.
     */
    public function advance(int $current, ?int $total, int $nextPage): void
    {
        $this->assertStatus('advance', SyncStatus::Running);

        if ($current < 0 || ($total !== null && ($total < 0 || $current > $total))) {
            throw new InvalidArgumentException(sprintf('Invalid progress %d of %s.', $current, $total ?? 'unknown'));
        }

        if ($nextPage < 1) {
            throw new InvalidArgumentException("Next page must be 1 or greater, {$nextPage} given.");
        }

        $this->progressCurrent = $current;
        $this->progressTotal = $total;
        $this->nextPage = $nextPage;
    }

    /**
     * Завершает прогон успешно: собрано всё, что площадка отдаёт.
     */
    public function complete(SyncStats $stats, DateTimeImmutable $now): void
    {
        $this->finish('complete', SyncStatus::Completed, $stats, null, $now);
    }

    /**
     * Завершает прогон частично: данные сохранены, но собрано меньше ожидаемого.
     *
     * @param  ErrorCode  $reason  Почему сбор неполон — например, отзывов пришло меньше, чем заявляет площадка.
     */
    public function completePartially(SyncStats $stats, ErrorCode $reason, DateTimeImmutable $now): void
    {
        $this->finish('complete partially', SyncStatus::Partial, $stats, SyncError::fromErrorCode($reason), $now);
    }

    /**
     * Завершает прогон ошибкой.
     */
    public function fail(ErrorCode $error, DateTimeImmutable $now): void
    {
        $this->assertStatus('fail', SyncStatus::Queued, SyncStatus::Running);

        $this->status = SyncStatus::Failed;
        $this->error = SyncError::fromErrorCode($error);
        $this->finishedAt = $now;
    }

    public function status(): SyncStatus
    {
        return $this->status;
    }

    public function progressCurrent(): int
    {
        return $this->progressCurrent;
    }

    public function progressTotal(): ?int
    {
        return $this->progressTotal;
    }

    /**
     * С какой страницы продолжится прерванный сбор: всё, что до неё, уже сохранено.
     */
    public function nextPage(): int
    {
        return $this->nextPage;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function error(): ?SyncError
    {
        return $this->error;
    }

    public function stats(): ?SyncStats
    {
        return $this->stats;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /**
     * Общий переход в конечное состояние: статус, статистика, причина и время завершения.
     */
    private function finish(string $action, SyncStatus $status, SyncStats $stats, ?SyncError $reason, DateTimeImmutable $now): void
    {
        $this->assertStatus($action, SyncStatus::Running);

        $this->status = $status;
        $this->stats = $stats;
        $this->error = $reason;
        $this->finishedAt = $now;
    }

    /**
     * Проверяет, что переход допустим из текущего статуса; иначе это ошибка в коде конвейера.
     */
    private function assertStatus(string $action, SyncStatus ...$allowed): void
    {
        if (! in_array($this->status, $allowed, true)) {
            throw InvalidSyncTransition::from($this->status, $action, array_values($allowed));
        }
    }
}
