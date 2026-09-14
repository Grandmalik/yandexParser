<?php

declare(strict_types=1);

namespace App\Modules\Sync\Domain;

/**
 * Решает, считать ли сбор полным (ADR-001, D9). Пока мы листаем страницы, отзывы на площадке появляются и
 * исчезают, поэтому точного совпадения не требуем — небольшое расхождение допустимо.
 */
final readonly class CompletenessPolicy
{
    public function __construct(private float $tolerance) {}

    /**
     * Полон ли сбор.
     *
     * @param  int  $collected  Сколько отзывов собрано на самом деле.
     * @param  int  $expected  Сколько площадка готова отдать: её собственный счётчик, обрезанный пределом выдачи.
     */
    public function isComplete(int $collected, int $expected): bool
    {
        return $collected >= $expected - $this->allowedGap($expected);
    }

    /**
     * Допустимое расхождение в отзывах — доля от ожидаемого, но не меньше одного.
     */
    public function allowedGap(int $expected): int
    {
        return $expected === 0 ? 0 : max(1, (int) ceil($expected * $this->tolerance));
    }
}
