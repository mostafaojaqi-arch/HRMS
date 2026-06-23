<?php

namespace Tests\Feature;

use App\Livewire\HumanResource\Structure\TimeSheet;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimeSheetPageTest extends TestCase
{
    public function test_hr_user_can_open_time_sheet_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('structure-timesheet'));

        $response->assertOk();
        $response->assertSeeLivewire(TimeSheet::class);
    }

    public function test_non_hr_user_cannot_open_time_sheet_page(): void
    {
        $role = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $response = $this->actingAs($user)->get(route('structure-timesheet'));

        $response->assertForbidden();
    }
}
