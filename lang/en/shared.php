<?php

declare(strict_types=1);

return [

    'enums' => [
        'platform' => [
            'yandex' => 'Yandex Maps',
        ],
    ],

    'errors' => [
        'validation' => [
            'failed' => 'Please check the highlighted fields.',
        ],
        'auth' => [
            'unauthenticated' => 'Please sign in.',
            'forbidden' => 'You are not allowed to perform this action.',
            'session_expired' => 'Your session has expired. Reload the page and try again.',
        ],
        'resource' => [
            'not_found' => 'The requested data was not found.',
        ],
        'http' => [
            'method_not_allowed' => 'The request method is not supported.',
            'rate_limited' => 'Too many requests. Try again in :seconds s.',
            'error' => 'The request cannot be processed.',
        ],
        'internal' => 'Internal server error. Please try again later.',
    ],

];
