<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'private_disk' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'throw' => true],
    ],
];
