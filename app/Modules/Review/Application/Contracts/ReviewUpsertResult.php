<?php

declare(strict_types=1);

namespace App\Modules\Review\Application\Contracts;

/**
 * Итог сохранения пачки: сколько отзывов создано, изменено и осталось прежними.
 */
final readonly class ReviewUpsertResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $unchanged,
    ) {}

    /**
     * Складывает итоги страниц в итог всего прогона.
     */
    public function plus(self $other): self
    {
        return new self(
            $this->created + $other->created,
            $this->updated + $other->updated,
            $this->unchanged + $other->unchanged,
        );
    }
}
