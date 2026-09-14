<?php

declare(strict_types=1);

namespace App\Modules\Review\Interfaces\Http\Controllers;

use App\Modules\Organization\Application\Contracts\OrganizationDirectory;
use App\Modules\Review\Application\Contracts\ReviewCatalog;
use App\Modules\Review\Interfaces\Http\Data\ReviewData;
use App\Modules\Shared\Interfaces\Http\AuthenticatedUser;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\Response;

final readonly class ReviewController
{
    public function __construct(private OrganizationDirectory $organizations) {}

    /**
     * Отдаёт страницу отзывов организации. Размер страницы задаёт конфигурация: клиент читает `meta.per_page`,
     * а не выбирает его сам.
     *
     * @return PaginatedDataCollection<int, ReviewData>
     */
    public function index(string $organization, Request $request, ReviewCatalog $reviews): PaginatedDataCollection
    {
        abort_unless(
            $this->organizations->isMember($organization, AuthenticatedUser::id($request)),
            Response::HTTP_NOT_FOUND,
        );

        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return ReviewData::collect(
            $reviews->page($organization, (int) $request->integer('page', 1), config()->integer('reviews.per_page')),
            PaginatedDataCollection::class,
        );
    }
}
