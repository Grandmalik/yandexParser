<?php

declare(strict_types=1);

/*
| Засевает одноразовую базу, на которой идёт сквозной сценарий: один пользователь, одна организация с показателями
| и столько отзывов, чтобы хватило на три страницы. Трогать что-либо, кроме своего SQLite-файла, отказывается.
*/

use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Organization\Domain\RatingSummary;
use App\Modules\Review\Application\Contracts\IncomingReview;
use App\Modules\Review\Application\Contracts\ReviewStore;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

/** @var Illuminate\Foundation\Application $app */
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = __DIR__.DIRECTORY_SEPARATOR.'e2e.sqlite';

if (file_exists($database)) {
    unlink($database);
}

touch($database);

config([
    'database.default' => 'e2e',
    'database.connections.e2e' => [
        'driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true,
    ],
    'cache.default' => 'array',
]);

if (DB::connection()->getDatabaseName() !== $database) {
    exit("Refusing to seed: connected to another database.\n");
}

$app->make(Kernel::class)->call('migrate', ['--force' => true, '--no-interaction' => true]);
$app->make(Kernel::class)->call('db:seed', ['--force' => true, '--no-interaction' => true]);

$repository = app(OrganizationRepository::class);
$organization = $repository->saveIfAbsent(Organization::connect(
    OrganizationId::generate(),
    new SourceReference(Platform::Yandex, '1703836794'),
    'https://yandex.ru/maps/org/1703836794/',
));
$repository->addMember($organization->id, (int) DB::table('users')->value('id'));

$summary = new RatingSummary(4.5, 416, 191);
$observedAt = new DateTimeImmutable('2026-09-12 10:00:00');
$organization->describe('Вольт 11', 'ул. Савина, 20');
$organization->recordFigures($summary, $observedAt);
$repository->save($organization);
$repository->addSnapshot($organization->id, null, $summary, $observedAt);

$reviews = [];

for ($i = 1; $i <= 120; $i++) {
    $reviews[] = new IncomingReview(
        externalId: "e2e-{$i}",
        // У каждого десятого отзыва скрыт автор, у каждого пятого нет оценки — оба случая реальны на площадке.
        authorName: $i % 10 === 0 ? null : "Автор {$i}",
        publishedAt: new DateTimeImmutable('2026-09-01 12:00:00'),
        text: "Отзыв номер {$i} про обслуживание и цены.",
        rating: $i % 5 === 0 ? null : ($i % 5) + 1,
        businessReply: $i % 7 === 0 ? 'Спасибо за отзыв!' : null,
    );
}

app(ReviewStore::class)->upsert($organization->id->value, null, $reviews, new DateTimeImmutable);

printf("seeded %s: %d reviews\n", $database, DB::table('reviews')->count());
