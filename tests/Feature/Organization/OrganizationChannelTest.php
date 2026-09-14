<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Persistence\User;
use App\Modules\Organization\Application\Contracts\OrganizationChannel;

/*
| Слушать организацию можно по тому же правилу, по которому её читают: только участникам. Драйвер «null» в тестовом
| окружении не авторизует вообще ничего, поэтому здесь поднимается настоящий брокер с одноразовыми учётными данными.
*/
beforeEach(function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-id',
    ]);

    // Каналы регистрируются на том брокере, который был текущим в момент загрузки файла, — при старте это был
    // драйвер «null» тестового окружения, поэтому файл загружается заново уже для нужного драйвера.
    require base_path('routes/channels.php');

    $this->user = User::factory()->create();
    $this->organizationId = organizationFor($this->user->id);
});

function authorizeChannel(string $organizationId): Illuminate\Testing\TestResponse
{
    return test()->post('/broadcasting/auth', [
        'channel_name' => 'private-'.OrganizationChannel::for($organizationId),
        'socket_id' => '1234.5678',
    ]);
}

it('lets a member listen to their organization', function (): void {
    $this->actingAs($this->user)->fromSpa();

    authorizeChannel($this->organizationId)->assertOk()->assertJsonStructure(['auth']);
});

it('refuses a user who is not a member', function (): void {
    $stranger = organizationFor(User::factory()->create()->id, '52335293875');
    $this->actingAs($this->user)->fromSpa();

    authorizeChannel($stranger)->assertForbidden();
});

it('refuses a visitor without a session', function (): void {
    authorizeChannel($this->organizationId)->assertForbidden();
});
