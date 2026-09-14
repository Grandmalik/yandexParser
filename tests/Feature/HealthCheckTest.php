<?php

declare(strict_types=1);

it('reports the application as healthy', function (): void {
    $this->get('/up')->assertOk();
});
