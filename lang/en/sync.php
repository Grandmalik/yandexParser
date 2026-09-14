<?php

declare(strict_types=1);

return [

    'enums' => [
        'status' => [
            'queued' => 'Queued',
            'running' => 'Collecting data',
            'completed' => 'Done',
            'partial' => 'Partially collected',
            'failed' => 'Failed',
        ],
    ],

    'errors' => [
        'sync' => [
            'already_running' => 'Data for this organization is already being collected. Please wait for the current run to finish.',
            'internal' => 'Collecting the data failed because of an internal error. We have been notified.',
        ],
    ],

];
