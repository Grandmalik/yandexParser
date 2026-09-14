<?php

declare(strict_types=1);

namespace App\Modules\Organization\Application;

use App\Modules\Organization\Application\Contracts\Events\OrganizationConnected;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Scraping\Application\Contracts\SourceUrlResolver;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

/**
 * Подключение карточки организации по ссылке.
 */
final readonly class ConnectOrganization
{
    public function __construct(
        private SourceUrlResolver $sources,
        private OrganizationRepository $organizations,
        private ConnectionInterface $connection,
        private Dispatcher $events,
    ) {}

    /**
     * Идемпотентно: повторное подключение той же карточки — тем же или другим пользователем — переиспользует
     * уже сохранённую организацию.
     *
     * @throws InvalidSourceUrl
     * @throws SourceUnavailable
     */
    public function handle(int $userId, string $url): OrganizationView
    {
        // Разбор ссылки может сходить на площадку (короткие ссылки), поэтому он вне транзакции.
        $source = $this->sources->resolve($url);

        return $this->connection->transaction(function () use ($userId, $source): OrganizationView {
            $organization = $this->organizations->saveIfAbsent(
                Organization::connect(OrganizationId::generate(), $source->reference, $source->canonicalUrl),
            );

            $this->organizations->addMember($organization->id, $userId);
            $this->events->dispatch(new OrganizationConnected($organization->id->value, $userId));

            return OrganizationView::fromOrganization($organization);
        });
    }
}
