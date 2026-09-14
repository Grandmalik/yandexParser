<?php

declare(strict_types=1);

return [

    'enums' => [
        'source_status' => [
            'healthy' => 'Working',
            'degraded' => 'Reading the platform keeps failing',
            'paused' => 'Requests to the platform are paused',
        ],
    ],

    'errors' => [
        'source' => [
            'invalid_url' => 'This does not look like a link. Paste a link to an organization card.',
            'unsupported_host' => 'Links from :host are not supported. Paste a link to an organization on Yandex Maps.',
            'not_an_organization' => 'The link does not point to an organization card. Open the organization on Yandex Maps and copy the link from the address bar or the "Share" button.',
            'not_found' => 'The organization was not found on the platform: the card may have been removed or closed.',
            'unavailable' => 'The data source is temporarily unavailable. Please try again later.',
            'rate_limited' => 'The platform asks to slow down. Data collection will continue later.',
            'blocked' => 'The platform temporarily restricted access (bot check). Data collection will be retried later.',
            'drift' => 'The platform changed its data format, collection is temporarily impossible. We have been notified and are fixing the parser.',
            'partial' => 'The platform served fewer reviews than its own counter shows. Everything it served is stored — this is a limit of the platform, not a failure of the collection.',
        ],
    ],

];
