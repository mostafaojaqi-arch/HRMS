<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LinkPrestoXLToSpeedUP extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:link-prestoXL-speedup
        {--create-tables : Create necessary table structures}
        {--rebuild-map : Rebuild the linkage mapping from scratch}
        {--truncate : Truncate tables before rebuild}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Link PrestoXL employees to SpeedUP personnel based on name matching';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ((bool) $this->option('create-tables')) {
            return $this->createTables();
        }

        if ((bool) $this->option('rebuild-map')) {
            return $this->rebuildLinkageMap();
        }

        $this->info('Use --create-tables to set up table structure or --rebuild-map to create linkage mapping.');

        return self::SUCCESS;
    }

    private function createTables(): int
    {
        $this->info('Creating linkage table structure...');

        try {
            // Create speedup_personnel table (rename from personnel_employees SpeedUP data)
            if (! Schema::hasTable('speedup_personnel')) {
                Schema::create('speedup_personnel', function (Blueprint $table): void {
                    $table->bigIncrements('id');
                    $table->string('personelid')->index();
                    $table->string('kurumkodu')->index();
                    $table->string('personnel_code')->nullable();
                    $table->string('personnel_name')->nullable();
                    $table->string('personnel_surname')->nullable();
                    $table->timestamps();
                });
                $this->info('✓ Created speedup_personnel table');
            }

            // Create prestoXL_employees table (for PrestoXL employee data)
            if (! Schema::hasTable('prestoXL_employees')) {
                Schema::create('prestoXL_employees', function (Blueprint $table): void {
                    $table->bigIncrements('id');
                    $table->string('per_id')->nullable()->unique();
                    $table->string('personnel_name')->nullable()->index();
                    $table->string('personnel_surname')->nullable()->index();
                    $table->string('personnel_code')->nullable();
                    $table->string('personnel_identity')->nullable();
                    $table->longText('company')->nullable();
                    $table->longText('source_schema')->nullable();
                    $table->timestamps();
                });
                $this->info('✓ Created prestoXL_employees table');
            }

            // Create personnel_linkage_map table
            if (! Schema::hasTable('personnel_linkage_map')) {
                Schema::create('personnel_linkage_map', function (Blueprint $table): void {
                    $table->bigIncrements('id');
                    $table->unsignedBigInteger('prestoXL_employee_id')->nullable()->index();
                    $table->unsignedBigInteger('speedup_personel_id')->nullable()->index();
                    $table->string('speedup_personelid')->nullable();
                    $table->string('speedup_kurumkodu')->nullable();
                    $table->decimal('match_score', 5, 2)->default(0);
                    $table->enum('match_type', ['automatic', 'manual', 'pending'])->default('pending');
                    $table->longText('notes')->nullable();
                    $table->timestamps();

                    $table->foreign('prestoXL_employee_id')
                        ->references('id')
                        ->on('prestoXL_employees')
                        ->onDelete('cascade');
                });
                $this->info('✓ Created personnel_linkage_map table');
            }

            $this->info('Table structure created successfully.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error("Failed to create tables: {$exception->getMessage()}");

            return self::FAILURE;
        }
    }

    private function rebuildLinkageMap(): int
    {
        $this->info('Building linkage map between PrestoXL and SpeedUP...');

        try {
            if ((bool) $this->option('truncate')) {
                DB::table('personnel_linkage_map')->truncate();
                $this->info('Truncated personnel_linkage_map');
            }

            // Get all PrestoXL employees
            $prestoXLEmployees = DB::table('prestoXL_employees')->get();
            $speedupPersonnel = DB::table('speedup_personnel')->get();

            if ($prestoXLEmployees->isEmpty()) {
                $this->warn('No PrestoXL employees found. Import PrestoXL data first.');

                return self::FAILURE;
            }

            if ($speedupPersonnel->isEmpty()) {
                $this->warn('No SpeedUP personnel found. Import SpeedUP data first.');

                return self::FAILURE;
            }

            $matchCount = 0;
            $pendingCount = 0;

            foreach ($prestoXLEmployees as $prestoEmployee) {
                $prestoName = $this->normalizeName(
                    $prestoEmployee->personnel_name ?? '',
                    $prestoEmployee->personnel_surname ?? ''
                );

                $bestMatch = null;
                $bestScore = 0;

                foreach ($speedupPersonnel as $speedupEmp) {
                    $speedupName = $this->normalizeName(
                        $speedupEmp->personnel_name ?? '',
                        $speedupEmp->personnel_surname ?? ''
                    );

                    $score = $this->calculateSimilarity($prestoName, $speedupName);

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = $speedupEmp;
                    }
                }

                // Match threshold: 0.80 (80% similarity)
                if ($bestMatch && $bestScore >= 0.80) {
                    DB::table('personnel_linkage_map')->insert([
                        'prestoXL_employee_id' => $prestoEmployee->id,
                        'speedup_personelid' => $bestMatch->personelid,
                        'speedup_kurumkodu' => $bestMatch->kurumkodu,
                        'match_score' => $bestScore,
                        'match_type' => 'automatic',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $matchCount++;
                } else {
                    // Create pending entry for manual review
                    DB::table('personnel_linkage_map')->insert([
                        'prestoXL_employee_id' => $prestoEmployee->id,
                        'match_score' => $bestScore,
                        'match_type' => 'pending',
                        'notes' => $bestMatch ? "Best match: {$bestMatch->personnel_name} {$bestMatch->personnel_surname} ({$bestScore})" : 'No candidate matches',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $pendingCount++;
                }
            }

            $this->info("✓ Linked $matchCount employees automatically");
            $this->warn("⚠ $pendingCount employees need manual review");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error("Linkage mapping failed: {$exception->getMessage()}");

            return self::FAILURE;
        }
    }

    private function normalizeName(string $firstName, string $lastName): string
    {
        $name = trim("{$firstName} {$lastName}");
        $name = mb_strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);

        if ($len1 === 0 && $len2 === 0) {
            return 1.0;
        }

        if ($len1 === 0 || $len2 === 0) {
            return 0.0;
        }

        // Levenshtein distance similarity
        $distance = levenshtein($str1, $str2);
        $maxLen = max($len1, $len2);

        return 1 - ($distance / $maxLen);
    }
}
