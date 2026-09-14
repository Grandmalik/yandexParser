<?php

declare(strict_types=1);

use App\Modules\Scraping\Infrastructure\Persistence\SourcePayloadModel;

/**
 * Сырые ответы — это улика для починки парсера, а не данные, которые мы храним: это самые большие строки в базе.
 */
function recordedPayload(string $kind, int $daysAgo): void
{
    SourcePayloadModel::query()->create([
        'platform' => 'yandex',
        'kind' => $kind,
        'http_status' => 200,
        'body' => '{}',
        'created_at' => now()->subDays($daysAgo),
    ]);
}

it('removes payloads older than the retention window and keeps the rest', function (): void {
    config(['scraping.payloads.retention_days' => 7]);
    recordedPayload('organization_page', 8);
    recordedPayload('reviews_page', 1);

    $this->artisan('model:prune', ['--model' => [SourcePayloadModel::class]])->assertSuccessful();

    expect(SourcePayloadModel::query()->count())->toBe(1);
    $this->assertDatabaseHas('source_payloads', ['kind' => 'reviews_page']);
});
