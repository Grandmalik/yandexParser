<?php

declare(strict_types=1);

namespace App\Modules\Organization\Domain;

use App\Modules\Shared\Domain\Source\SourceReference;
use DateTimeImmutable;

/**
 * Карточка организации на картографической площадке. Уникальна по источнику: подключив одну и ту же карточку,
 * пользователи работают с общей записью, а не с копиями.
 */
final class Organization
{
    private function __construct(
        public readonly OrganizationId $id,
        public readonly SourceReference $source,
        public readonly string $sourceUrl,
        private ?string $name,
        private ?string $address,
        private ?RatingSummary $ratingSummary,
        private ?DateTimeImmutable $metricsUpdatedAt,
    ) {}

    /**
     * Только что подключённая карточка: её данные неизвестны до первого сбора.
     */
    public static function connect(OrganizationId $id, SourceReference $source, string $sourceUrl): self
    {
        return new self($id, $source, $sourceUrl, null, null, null, null);
    }

    /**
     * Восстанавливает агрегат из хранилища.
     */
    public static function restore(
        OrganizationId $id,
        SourceReference $source,
        string $sourceUrl,
        ?string $name,
        ?string $address,
        ?RatingSummary $ratingSummary,
        ?DateTimeImmutable $metricsUpdatedAt,
    ): self {
        return new self($id, $source, $sourceUrl, $name, $address, $ratingSummary, $metricsUpdatedAt);
    }

    /**
     * Запоминает название и адрес, прочитанные с площадки.
     */
    public function describe(string $name, ?string $address): void
    {
        $this->name = $name;
        $this->address = $address;
    }

    /**
     * Принимает показатели, замеченные на площадке, и возвращает предыдущие — чтобы было о чём сообщить,
     * если они изменились.
     */
    public function recordFigures(RatingSummary $summary, DateTimeImmutable $observedAt): ?RatingSummary
    {
        $previous = $this->ratingSummary;

        $this->ratingSummary = $summary;
        $this->metricsUpdatedAt = $observedAt;

        return $previous;
    }

    public function name(): ?string
    {
        return $this->name;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    public function ratingSummary(): ?RatingSummary
    {
        return $this->ratingSummary;
    }

    public function metricsUpdatedAt(): ?DateTimeImmutable
    {
        return $this->metricsUpdatedAt;
    }
}
