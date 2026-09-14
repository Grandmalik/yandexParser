<?php

declare(strict_types=1);

use App\Modules\Organization\Domain\Organization;
use App\Modules\Organization\Domain\OrganizationId;
use App\Modules\Organization\Domain\OrganizationRepository;
use App\Modules\Shared\Domain\Source\Platform;
use App\Modules\Shared\Domain\Source\SourceReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Feature-тесты поднимают фреймворк; Unit и Arch обходятся без него.
*/
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * Организация, подключённая указанным пользователем, — готова для тестов, которые её читают или собирают.
 */
function organizationFor(int $userId, string $externalId = '1703836794'): string
{
    $repository = app(OrganizationRepository::class);
    $organization = $repository->saveIfAbsent(Organization::connect(
        OrganizationId::generate(),
        new SourceReference(Platform::Yandex, $externalId),
        "https://yandex.ru/maps/org/{$externalId}/",
    ));
    $repository->addMember($organization->id, $userId);

    return $organization->id->value;
}

/**
 * Сырое содержимое записанного ответа площадки из tests/Fixtures (см. ai/research/yandex-source.md).
 */
function recordedResponse(string $path): string
{
    $content = file_get_contents(__DIR__.'/Fixtures/'.$path);

    return $content !== false ? $content : throw new RuntimeException("Fixture {$path} is missing.");
}

/**
 * Записанный JSON-ответ в разобранном виде — его удобно поправить, чтобы изобразить смену формата площадкой.
 *
 * @return array<array-key, mixed>
 */
function recordedJson(string $path): array
{
    $decoded = json_decode(recordedResponse($path), true, flags: JSON_THROW_ON_ERROR);

    return is_array($decoded) ? $decoded : throw new RuntimeException("Fixture {$path} is not a JSON object.");
}
