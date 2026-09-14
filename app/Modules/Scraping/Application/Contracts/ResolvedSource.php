<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Разобранная ссылка: адрес карточки на площадке и канонический URL, который мы храним.
 */
final readonly class ResolvedSource
{
    public function __construct(
        public SourceReference $reference,
        public string $canonicalUrl,
    ) {}
}
