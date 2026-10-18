<?php

declare(strict_types=1);

return [
    'default' => env('BROADCAST_CONNECTION', 'log'),
    'connections' => [
        'log' => ['driver' => 'log'],
        'null' => ['driver' => 'null'],
    ],
];
