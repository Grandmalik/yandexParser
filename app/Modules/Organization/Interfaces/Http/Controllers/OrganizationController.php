<?php

declare(strict_types=1);

namespace App\Modules\Organization\Interfaces\Http\Controllers;

use App\Modules\Organization\Application\ConnectOrganization;
use App\Modules\Organization\Application\OrganizationQueries;
use App\Modules\Organization\Interfaces\Http\Data\ConnectOrganizationData;
use App\Modules\Organization\Interfaces\Http\Data\MetricsSnapshotData;
use App\Modules\Organization\Interfaces\Http\Data\OrganizationData;
use App\Modules\Scraping\Application\Contracts\Exceptions\InvalidSourceUrl;
use App\Modules\Shared\Interfaces\Http\AuthenticatedUser;
use App\Modules\Shared\Interfaces\Http\Errors\ErrorMessages;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\Response;

final readonly class OrganizationController
{
    public function __construct(private OrganizationQueries $organizations) {}

    /**
     * Подключает карточку по ссылке. Ответ 202: организация сохранена, данные собираются в фоне.
     */
    public function store(
        ConnectOrganizationData $data,
        Request $request,
        ConnectOrganization $connect,
        ErrorMessages $messages,
    ): Response {
        try {
            $organization = $connect->handle(AuthenticatedUser::id($request), $data->url);
        } catch (InvalidSourceUrl $exception) {
            // Ссылка не на карточку организации — это ошибка поля формы, и показать её нужно рядом с полем.
            throw ValidationException::withMessages([
                'url' => $messages->translate($exception->errorCode->messageKey(), $exception->messageParameters),
            ]);
        }

        return OrganizationData::from($organization)->toResponse($request)->setStatusCode(Response::HTTP_ACCEPTED);
    }

    /**
     * Отдаёт страницу списка организаций пользователя. Размер страницы задаёт конфигурация: клиент читает
     * `meta.per_page`, а не выбирает его сам.
     *
     * @return PaginatedDataCollection<int, OrganizationData>
     */
    public function index(Request $request): PaginatedDataCollection
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return OrganizationData::collect(
            $this->organizations->forMember(
                AuthenticatedUser::id($request),
                (int) $request->integer('page', 1),
                config()->integer('organization.per_page'),
            ),
            PaginatedDataCollection::class,
        );
    }

    public function show(string $organization, Request $request): OrganizationData
    {
        $view = $this->organizations->find($organization, AuthenticatedUser::id($request));

        abort_if($view === null, Response::HTTP_NOT_FOUND);

        return OrganizationData::fromOrganizationView($view);
    }

    /**
     * История показателей площадки, новые сверху.
     *
     * @return DataCollection<int, MetricsSnapshotData>
     */
    public function history(string $organization, Request $request): DataCollection
    {
        $history = $this->organizations->history(
            $organization,
            AuthenticatedUser::id($request),
            config()->integer('organization.history_limit'),
        );

        abort_if($history === null, Response::HTTP_NOT_FOUND);

        return MetricsSnapshotData::collect(
            array_map(MetricsSnapshotData::fromMetricsSnapshotView(...), $history),
            DataCollection::class,
        );
    }
}
