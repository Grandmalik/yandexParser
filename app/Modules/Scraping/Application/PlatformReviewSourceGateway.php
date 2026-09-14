<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application;

use App\Modules\Scraping\Application\Contracts\ReviewPage;
use App\Modules\Scraping\Application\Contracts\ReviewSourceGateway;
use App\Modules\Scraping\Application\Contracts\SourceOrganization;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use LogicException;

/**
 * Направляет каждый источник в тот шлюз, который настроен для его площадки.
 */
final readonly class PlatformReviewSourceGateway implements ReviewSourceGateway
{
    /**
     * @param  array<string, ReviewSourceGateway>  $gateways  Ключ — значение площадки.
     */
    public function __construct(private array $gateways) {}

    public function fetchOrganization(SourceReference $source): SourceOrganization
    {
        return $this->gatewayFor($source->platform)->fetchOrganization($source);
    }

    /**
     * @return iterable<int, ReviewPage>
     */
    public function fetchReviews(SourceReference $source, int $fromPage = 1): iterable
    {
        return $this->gatewayFor($source->platform)->fetchReviews($source, $fromPage);
    }

    /**
     * Шлюз площадки; не настроен — ошибка конфигурации, а не молчаливый пропуск.
     */
    private function gatewayFor(Platform $platform): ReviewSourceGateway
    {
        return $this->gateways[$platform->value]
            ?? throw new LogicException("No review source gateway is configured for platform \"{$platform->value}\".");
    }
}
