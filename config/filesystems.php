<?php

return [
    'default' => env('FILESYSTEM_DISK', 'phar'),
    'disks' => [
        'phar' => [
            'driver' => 'local',
            'root' => getcwd(),
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path(),
        ],
    ],
];
