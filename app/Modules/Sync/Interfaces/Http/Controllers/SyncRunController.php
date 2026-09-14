<?php

declare(strict_types=1);

namespace App\Modules\Sync\Interfaces\Http\Controllers;

use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Shared\Interfaces\Http\AuthenticatedUser;
use App\Modules\Shared\Interfaces\Http\Errors\ErrorMessages;
use App\Modules\Sync\Application\GetSyncRun;
use App\Modules\Sync\Application\RequestSync;
use App\Modules\Sync\Application\SyncRunView;
use App\Modules\Sync\Domain\SyncRunId;
use App\Modules\Sync\Domain\SyncTrigger;
use App\Modules\Sync\Interfaces\Http\Data\SyncRunData;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Прогоны сбора: запуск и чтение состояния. Организация, к которой у пользователя нет доступа, не должна даже
 * выглядеть существующей — отсюда 404 вместо 403.
 */
final readonly class SyncRunController
{
    public function __construct(
        private ErrorMessages $messages,
        private OrganizationDirectory $organizations,
    ) {}

    /**
     * Запускает сбор заново. Ответ 202: работа поставлена в очередь, клиент следит за этим прогоном.
     */
    public function store(string $organization, Request $request, RequestSync $requestSync): Response
    {
        $this->assertMember($organization, $request);

        return $this->respond($requestSync->handle($organization, SyncTrigger::Manual), $request)
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Последний прогон организации — его состояние показывает экран.
     */
    public function latest(string $organization, Request $request, GetSyncRun $runs): Response
    {
        $run = $runs->latestForOrganization($organization, AuthenticatedUser::id($request));

        abort_if($run === null, Response::HTTP_NOT_FOUND);

        return $this->respond($run, $request);
    }

    /**
     * Конкретный прогон по его id.
     */
    public function show(string $syncRun, Request $request, GetSyncRun $runs): Response
    {
        $run = $runs->byId(SyncRunId::fromString($syncRun), AuthenticatedUser::id($request));

        abort_if($run === null, Response::HTTP_NOT_FOUND);

        return $this->respond($run, $request);
    }

    /**
     * 404, если пользователь не участник организации.
     */
    private function assertMember(string $organization, Request $request): void
    {
        abort_unless(
            $this->organizations->isMember($organization, AuthenticatedUser::id($request)),
            Response::HTTP_NOT_FOUND,
        );
    }

    /**
     * Превращает прогон в ответ API.
     */
    private function respond(SyncRunView $run, Request $request): Response
    {
        return SyncRunData::fromSyncRunView($run, $this->messages)->toResponse($request);
    }
}
