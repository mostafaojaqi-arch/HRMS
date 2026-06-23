<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('prestoXL_employees')) {
            return;
        }

        Schema::table('prestoXL_employees', function (Blueprint $table): void {
            if (! Schema::hasColumn('prestoXL_employees', 'speedup_personelid')) {
                $table->string('speedup_personelid')->nullable()->after('per_id');
            }

            if (! Schema::hasColumn('prestoXL_employees', 'speedup_kurumkodu')) {
                $table->string('speedup_kurumkodu')->nullable()->after('speedup_personelid');
            }
        });

        if (! $this->indexExists('prestoXL_employees', 'idx_speedup_link')) {
            Schema::table('prestoXL_employees', function (Blueprint $table): void {
                $table->index(['speedup_personelid', 'speedup_kurumkodu'], 'idx_speedup_link');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('prestoXL_employees')) {
            return;
        }

        Schema::table('prestoXL_employees', function (Blueprint $table): void {
            if ($this->indexExists('prestoXL_employees', 'idx_speedup_link')) {
                $table->dropIndex('idx_speedup_link');
            }

            if (Schema::hasColumn('prestoXL_employees', 'speedup_personelid')) {
                $table->dropColumn('speedup_personelid');
            }

            if (Schema::hasColumn('prestoXL_employees', 'speedup_kurumkodu')) {
                $table->dropColumn('speedup_kurumkodu');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
