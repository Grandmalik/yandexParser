<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Делает вид, что следующие запросы идут от SPA, — тогда Sanctum открывает cookie-сессию.
     */
    protected function fromSpa(): static
    {
        return $this->withHeader('Origin', config()->string('app.url'));
    }
}
