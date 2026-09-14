<?php

declare(strict_types=1);

namespace App\Modules\Scraping\Application\Contracts;

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceDrift;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceNotFound;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceRateLimited;
use App\Modules\Scraping\Application\Contracts\Exceptions\SourceUnavailable;
use App\Modules\Shared\Domain\Source\SourceReference;

/**
 * Читает данные организации с картографической площадки.
 */
interface ReviewSourceGateway
{
    /**
     * @throws SourceNotFound
     * @throws SourceDrift
     * @throws SourceBlocked
     * @throws SourceRateLimited
     * @throws SourceUnavailable
     */
    public function fetchOrganization(SourceReference $source): SourceOrganization;

    /**
     * Все отзывы, которые отдаёт площадка, новые сверху, по одной странице за раз: запросы делаются лениво, по
     * ходу обхода, поэтому потребитель успевает показывать прогресс и сохранять каждую страницу. Во время обхода
     * бросает те же исключения, что и fetchOrganization, кроме SourceNotFound.
     *
     * @param  int  $fromPage  С какого места продолжать после прерванного сбора; адаптеры, у которых лента
     *                         адресуется непрозрачным курсором, начинают сначала и полагаются на дедупликацию.
     * @return iterable<int, ReviewPage>
     */
    public function fetchReviews(SourceReference $source, int $fromPage = 1): iterable;
}
