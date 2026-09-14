<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
| Every page is rendered by the SPA; the API, Sanctum and the health check keep their own routes.
*/
Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|sanctum|up).*$')
    ->name('spa');
