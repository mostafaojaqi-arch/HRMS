<?php

namespace Tests\Feature;

use App\Livewire\HumanResource\Structure\TimeSheetLinkage;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimeSheetLinkagePageTest extends TestCase
{
    public function test_hr_user_can_open_timesheet_linkage_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('structure-timesheet-linkage'));

        $response->assertOk();
        $response->assertSeeLivewire(TimeSheetLinkage::class);
    }

    public function test_non_hr_user_cannot_open_timesheet_linkage_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('structure-timesheet-linkage'));

        $response->assertForbidden();
    }
}
