<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Error\DomainException;
use App\Modules\Shared\Domain\Error\ErrorCode;
use App\Modules\Shared\Domain\Error\ErrorKind;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

enum ApiErrorResponseTestCode: string implements ErrorCode
{
    case Conflict = 'test.conflict';

    public function code(): string
    {
        return $this->value;
    }

    public function kind(): ErrorKind
    {
        return ErrorKind::Conflict;
    }

    public function messageKey(): string
    {
        return 'test.errors.conflict';
    }
}

final class ApiErrorResponseTestException extends DomainException {}

beforeEach(function (): void {
    Route::prefix('api/v1/_test')->middleware('api')->group(function (): void {
        Route::post('validation', fn (Request $request) => $request->validate(['url' => ['required']]));
        Route::get('domain', fn () => throw new ApiErrorResponseTestException(ApiErrorResponseTestCode::Conflict, ['organization_id' => 7]));
        Route::get('crash', fn () => throw new RuntimeException('secret internals'));
        Route::get('auth', fn () => 'ok')->middleware('auth');
        Route::get('throttled', fn () => 'ok')->middleware('throttle:1,1');
    });
});

it('renders an unknown API route as a not found error', function (): void {
    $this->getJson('/api/v1/missing')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'resource.not_found')
        ->assertJsonPath('error.message', __('shared.errors.resource.not_found'))
        ->assertJsonStructure(['error' => ['code', 'message', 'fields', 'details', 'trace_id']]);
});

it('renders JSON for API paths even without an Accept header', function (): void {
    $this->get('/api/v1/missing')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'resource.not_found');
});

it('serves the SPA for pages, so only the API answers with the error envelope', function (): void {
    $response = $this->withoutVite()->get('/some-page')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/html')
        ->and($response->getContent())->toContain('id="app"');
});

it('exposes validation messages per field', function (): void {
    $this->postJson('/api/v1/_test/validation')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation.failed')
        ->assertJsonCount(1, 'error.fields.url');
});

it('maps a domain exception by its error kind and exposes its details', function (): void {
    $this->getJson('/api/v1/_test/domain')
        ->assertConflict()
        ->assertJsonPath('error.code', 'test.conflict')
        ->assertJsonPath('error.details.organization_id', 7);
});

it('hides internals of unexpected failures outside debug mode', function (): void {
    config(['app.debug' => false]);

    $this->getJson('/api/v1/_test/crash')
        ->assertInternalServerError()
        ->assertJsonPath('error.code', 'internal')
        ->assertJsonPath('error.details', null)
        ->assertDontSee('secret internals');
});

it('adds exception details in debug mode', function (): void {
    config(['app.debug' => true]);

    $this->getJson('/api/v1/_test/crash')
        ->assertInternalServerError()
        ->assertJsonPath('error.details.debug.exception', RuntimeException::class);
});

it('renders unauthenticated access', function (): void {
    $this->getJson('/api/v1/_test/auth')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'auth.unauthenticated');
});

it('tells when to retry after hitting a rate limit', function (): void {
    $this->freezeTime();

    $this->getJson('/api/v1/_test/throttled')->assertOk();

    $this->getJson('/api/v1/_test/throttled')
        ->assertTooManyRequests()
        ->assertJsonPath('error.code', 'http.rate_limited')
        ->assertJsonPath('error.details.retry_after', 60)
        ->assertHeader('Retry-After', '60');
});

it('echoes a valid incoming trace id in the header and the error body', function (): void {
    $this->getJson('/api/v1/missing', ['X-Request-Id' => 'proxy-trace-123'])
        ->assertHeader('X-Request-Id', 'proxy-trace-123')
        ->assertJsonPath('error.trace_id', 'proxy-trace-123');
});

it('replaces a malformed incoming trace id', function (): void {
    $response = $this->getJson('/api/v1/missing', ['X-Request-Id' => 'bad id!']);

    $traceId = $response->headers->get('X-Request-Id');

    expect($traceId)->not->toBe('bad id!')->toBeString()
        ->and($response->json('error.trace_id'))->toBe($traceId);
});
