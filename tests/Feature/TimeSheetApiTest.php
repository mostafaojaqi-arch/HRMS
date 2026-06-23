<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeSheetApiTest extends TestCase
{
    public function test_timesheet_api_requires_authentication(): void
    {
        $response = $this->getJson('/api/timesheet/link-status');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_access_timesheet_link_status_api(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/timesheet/link-status');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ok',
            'key',
            'total_personnel_rows',
            'linked_rows',
            'unlinked_rows',
        ]);
    }

    public function test_daily_api_excludes_weekend_holiday_from_absence_and_uses_integer_late_early_values(): void
    {
        Schema::dropIfExists('personnel_employees');
        Schema::dropIfExists('personnel_time_reports');

        Schema::create('personnel_employees', function (Blueprint $table): void {
            $table->id('import_id');
            $table->string('personelid')->nullable();
            $table->string('kurumkodu')->nullable();
            $table->string('personnel_code')->nullable();
            $table->string('personnel_name')->nullable();
            $table->string('personnel_surname')->nullable();
            $table->timestamps();
        });

        Schema::create('personnel_time_reports', function (Blueprint $table): void {
            $table->id('import_id');
            $table->string('personelid')->nullable();
            $table->string('kurumkodu')->nullable();
            $table->string('harekettarihi')->nullable();
            $table->string('giris')->nullable();
            $table->string('cikis')->nullable();
            $table->string('igecgelme')->nullable();
            $table->string('gecgelme')->nullable();
            $table->string('ierkencikma')->nullable();
            $table->string('erkencikma')->nullable();
            $table->string('gunlukdurum')->nullable();
            $table->timestamps();
        });

        DB::table('personnel_employees')->insert([
            'personelid' => '1001',
            'kurumkodu' => 'STERNTEK',
            'personnel_code' => 'E1001',
            'personnel_name' => 'John',
            'personnel_surname' => 'Doe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('personnel_time_reports')->insert([
            [
                'personelid' => '1001',
                'kurumkodu' => 'STERNTEK',
                'harekettarihi' => '2026-06-14',
                'giris' => null,
                'cikis' => null,
                'igecgelme' => '0',
                'gecgelme' => '00:30',
                'ierkencikma' => '0',
                'erkencikma' => '00:20',
                'gunlukdurum' => 'HAFTA TATİLİ',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'personelid' => '1001',
                'kurumkodu' => 'STERNTEK',
                'harekettarihi' => '2026-06-15',
                'giris' => '09:10',
                'cikis' => '17:00',
                'igecgelme' => '5',
                'gecgelme' => '00:30',
                'ierkencikma' => '7',
                'erkencikma' => '00:20',
                'gunlukdurum' => 'GENEL ÇALIŞMA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'personelid' => '1001',
                'kurumkodu' => 'STERNTEK',
                'harekettarihi' => '2026-06-16',
                'giris' => null,
                'cikis' => null,
                'igecgelme' => '0',
                'gecgelme' => '00:00',
                'ierkencikma' => '0',
                'erkencikma' => '00:00',
                'gunlukdurum' => 'GENEL ÇALIŞMA',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/timesheet/daily?date_from=2026-06-01&date_to=2026-06-30&per_page=50');

        $response->assertOk();
        $response->assertJsonPath('summary.absences', 1);
        $response->assertJsonPath('summary.late_entries', 1);
        $response->assertJsonPath('summary.early_exits', 1);

        $this->assertSame(5, (int) data_get($response->json(), 'data.1.late_minutes'));
        $this->assertSame(7, (int) data_get($response->json(), 'data.1.early_exit_minutes'));
    }
}
