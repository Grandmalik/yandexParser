<?php

declare(strict_types=1);

use App\Modules\Scraping\Application\Contracts\Exceptions\SourceBlocked;
use App\Modules\Scraping\Infrastructure\Http\CachedProxyPool;
use App\Modules\Scraping\Infrastructure\Http\Proxy;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Tests\Support\TestClock;

beforeEach(function (): void {
    $this->clock = new TestClock;
    $this->first = Proxy::fromUrl('http://user:secret@10.0.0.1:8080');
    $this->second = Proxy::fromUrl('http://user:secret@10.0.0.2:8080');
    $this->pool = new CachedProxyPool([$this->first, $this->second], new Repository(new ArrayStore), $this->clock);
});

it('rotates routes, least recently used first', function (): void {
    $used = array_map(fn (): string => $this->pool->acquire()->id, range(1, 4));

    expect($used)->toBe([$this->first->id, $this->second->id, $this->first->id, $this->second->id]);
});

it('skips a quarantined route until its ban expires', function (): void {
    $this->pool->ban($this->first, 60);

    expect($this->pool->acquire()->id)->toBe($this->second->id)
        ->and($this->pool->acquire()->id)->toBe($this->second->id);

    $this->clock->advance(61_000);

    expect($this->pool->acquire()->id)->toBe($this->first->id);
});

it('refuses to hand out a route when all are banned and tells when one frees up', function (): void {
    $this->pool->ban($this->first, 600);
    $this->pool->ban($this->second, 120);

    expect(fn () => $this->pool->acquire())
        ->toThrow(fn (SourceBlocked $blocked) => expect($blocked->retryAfterSeconds)->toBe(120));
});

it('never shortens an existing ban', function (): void {
    $this->pool->ban($this->first, 600);
    $this->pool->ban($this->first, 60);
    $this->clock->advance(61_000);

    expect($this->pool->acquire()->id)->toBe($this->second->id);
});

it('never exposes proxy credentials in the route id', function (): void {
    expect($this->first->id)->not->toContain('secret')->not->toContain('10.0.0.1')
        ->and(Proxy::direct()->url)->toBeNull();
});
