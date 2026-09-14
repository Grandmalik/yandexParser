<?php

declare(strict_types=1);

namespace App\Modules\Organization\Infrastructure\Persistence;

use App\Modules\Organization\Application\Contracts\ConnectedOrganization;
use App\Modules\Organization\Application\Contracts\Events\OrganizationMetricsChanged;
use App\Modules\Organization\Application\Contracts\MetricsChange;
use App\Modules\Organization\Application\Contracts\OrganizationCard;
use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Organization\Application\Contracts\RatingFigures;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Organization\Domain\RatingSummary;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class EloquentOrganizationDirectory implements OrganizationDirectory
{
    public function __construct(
        private OrganizationRepository $organizations,
        private ConnectionInterface $connection,
        private Dispatcher $events,
    ) {}

    public function find(string $organizationId): ?ConnectedOrganization
    {
        $organization = $this->organizations->findById(OrganizationId::fromString($organizationId));

        return $organization === null ? null : new ConnectedOrganization(
            id: $organization->id->value,
            source: $organization->source,
            name: $organization->name(),
        );
    }

    public function isMember(string $organizationId, int $userId): bool
    {
        return $this->organizations->isMember(OrganizationId::fromString($organizationId), $userId);
    }

    public function dueForResync(DateTimeImmutable $lastReadBefore, int $limit): array
    {
        return array_map(
            static fn (Organization $organization): ConnectedOrganization => new ConnectedOrganization(
                id: $organization->id->value,
                source: $organization->source,
                name: $organization->name(),
            ),
            $this->organizations->listDueForResync($lastReadBefore, $limit),
        );
    }

    public function recordCard(string $organizationId, ?string $syncRunId, OrganizationCard $card, DateTimeImmutable $observedAt): MetricsChange
    {
        $id = OrganizationId::fromString($organizationId);

        return $this->connection->transaction(function () use ($id, $syncRunId, $card, $observedAt): MetricsChange {
            $organization = $this->organizations->findById($id)
                ?? throw new OrganizationMissing("Organization {$id->value} no longer exists.");

            $summary = new RatingSummary($card->figures->rating, $card->figures->ratingsCount, $card->figures->reviewsCount);
            $organization->describe($card->name, $card->address);
            $previous = $organization->recordFigures($summary, $observedAt);

            $this->organizations->save($organization);
            $this->organizations->addSnapshot($id, $syncRunId, $summary, $observedAt);

            $change = new MetricsChange($previous === null ? null : $this->figures($previous), $card->figures);

            if ($change->changed()) {
                $this->events->dispatch(new OrganizationMetricsChanged($id->value, $change->before, $change->after));
            }

            return $change;
        });
    }

    private function figures(RatingSummary $summary): RatingFigures
    {
        return new RatingFigures($summary->rating, $summary->ratingsCount, $summary->reviewsCount);
    }
}
