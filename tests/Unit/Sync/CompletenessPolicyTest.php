<?php

declare(strict_types=1);

use App\Modules\Sync\Domain\CompletenessPolicy;

beforeEach(function (): void {
    $this->policy = new CompletenessPolicy(tolerance: 0.01);
});

it('accepts a small gap: reviews come and go while we page through them', function (int $collected, int $expected, bool $complete): void {
    expect($this->policy->isComplete($collected, $expected))->toBe($complete);
})->with([
    'nothing to collect' => [0, 0, true],
    'everything collected' => [191, 191, true],
    'one review vanished' => [190, 191, true],
    'two of 191 missing' => [189, 191, true],
    'three of 191 missing' => [188, 191, false],
    'six of 600 missing' => [594, 600, true],
    'seven of 600 missing' => [593, 600, false],
    'half collected' => [100, 600, false],
    'more than expected' => [195, 191, true],
]);

it('tolerates at least one review even for tiny organizations', function (): void {
    expect($this->policy->allowedGap(1))->toBe(1)
        ->and($this->policy->allowedGap(0))->toBe(0)
        ->and($this->policy->allowedGap(191))->toBe(2)
        ->and($this->policy->allowedGap(600))->toBe(6);
});
