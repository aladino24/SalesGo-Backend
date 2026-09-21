<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;

abstract class ApiController extends Controller
{
    protected function branchId(User $user): int
    {
        return $user->branch_id;
    }

    protected function isSuperUser(User $user): bool
    {
        $user->loadMissing('role');

        return $user->role?->slug === 'it';
    }

    protected function userPayload(User $user): array
    {
        $user->loadMissing('branch', 'role', 'division');

        return ['id' => (string) $user->id, 'employeeCode' => $user->employee_code, 'branchId' => (string) $user->branch_id, 'branchCode' => $user->branch->code, 'divisionCode' => $user->division->code, 'name' => $user->name, 'role' => $user->role->slug, 'maxDevices' => max(1, (int) $user->max_devices)];
    }
}
