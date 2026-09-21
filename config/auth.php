<?php

use App\Models\User;

return [
    'defaults' => ['guard' => 'sanctum', 'passwords' => 'users'],
    'guards' => ['sanctum' => ['driver' => 'sanctum', 'provider' => 'users']],
    'providers' => ['users' => ['driver' => 'eloquent', 'model' => User::class]],
];
