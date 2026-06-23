<?php

namespace App\Console\Commands;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportSpeedupAttendanceData extends Command
{
    private const MAX_BIND_PARAMS_PER_QUERY = 65000;

    private const FAST_CHUNK_FLOOR = 500;

    protected $signature = 'attendance:import-speedup
        {--host=192.168.1.12 : SQL Server host}
        {--port=6588 : SQL Server port}
        {--username=BiUser : SQL Server username}
        {--password= : SQL Server password}
        {--database=SpeedUP : SQL Server database}
        {--chunk=1000 : Insert chunk size}
        {--truncate : Truncate destination tables before import}';

    protected $description = 'Import SpeedUP dbo.personel and dbo.gcRaporu into local hrms_database tables using English table names.';

    private const TABLE_MAP = [
        'personel' => 'personnel_employees',
        'gcRaporu' => 'personnel_time_reports',
    ];

    public function handle(): int
    {
        $password = (string) $this->option('password');

        if ($password === '') {
            $this->error('Missing --password option.');

            return self::FAILURE;
        }

        $connection = $this->configureExternalConnection();
        DB::connection()->disableQueryLog();
        DB::connection($connection)->disableQueryLog();

        foreach (self::TABLE_MAP as $source => $destination) {
            if (! $this->sourceTableExists($connection, $source)) {
                $this->warn("Skipping missing source table [dbo.$source].");

                continue;
            }

            $this->importTable($connection, $source, $destination, (int) $this->option('chunk'), (bool) $this->option('truncate'));
        }

        DB::disconnect($connection);
        DB::purge($connection);

        $this->info('SpeedUP attendance import finished successfully.');

        return self::SUCCESS;
    }

    private function configureExternalConnection(): string
    {
        $name = 'speedup_sqlsrv_attendance_import';

        config([
            'database.connections.'.$name => [
                'driver' => 'sqlsrv',
                'host' => (string) $this->option('host'),
                'port' => (string) $this->option('port'),
                'database' => (string) $this->option('database'),
                'username' => (string) $this->option('username'),
                'password' => (string) $this->option('password'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'encrypt' => 'false',
                'trust_server_certificate' => 'true',
            ],
        ]);

        DB::purge($name);

        return $name;
    }

    private function sourceTableExists(string $connectionName, string $sourceTable): bool
    {
        return DB::connection($connectionName)
            ->table('INFORMATION_SCHEMA.TABLES')
            ->where('TABLE_NAME', $sourceTable)
            ->where('TABLE_SCHEMA', 'dbo')
            ->exists();
    }

    private function importTable(
        string $connectionName,
        string $sourceTable,
        string $destinationTable,
        int $chunkSize,
        bool $truncate
    ): void {
        $sourceColumns = DB::connection($connectionName)
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', $sourceTable)
            ->where('TABLE_SCHEMA', 'dbo')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->map(static fn ($column): string => (string) $column)
            ->toArray();

        if ($sourceColumns === []) {
            $this->warn("Skipping [$sourceTable] because no columns were discovered.");

            return;
        }

        $columnMap = $this->buildColumnMap($sourceColumns);
        $translatedColumns = array_values($columnMap);

        $this->ensureDestinationTable($destinationTable, $translatedColumns);

        if ($truncate) {
            DB::table($destinationTable)->truncate();
        }

        $this->line("Importing [dbo.$sourceTable] -> [$destinationTable]...");

        $query = DB::connection($connectionName)->table('dbo.'.$sourceTable);
        $imported = 0;
        $chunkSize = $this->resolveInsertChunkSize($chunkSize, count($columnMap));

        $query->orderBy($sourceColumns[0])->chunk($chunkSize, function ($rows) use ($destinationTable, $columnMap, &$imported): void {
            $timestamp = now();
            $buffer = [];

            foreach ($rows as $row) {
                $record = [];

                foreach ($columnMap as $sourceColumn => $destinationColumn) {
                    $value = $row->{$sourceColumn} ?? null;

                    if (is_resource($value)) {
                        $value = stream_get_contents($value);
                    }

                    if ($value instanceof DateTimeInterface) {
                        $value = $value->format('Y-m-d H:i:s');
                    }

                    if (is_bool($value)) {
                        $value = $value ? '1' : '0';
                    }

                    if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
                        $value = 'base64:'.base64_encode($value);
                    }

                    $record[$destinationColumn] = $value;
                }

                $record['created_at'] = $timestamp;
                $record['updated_at'] = $timestamp;
                $buffer[] = $record;
            }

            if ($buffer !== []) {
                DB::table($destinationTable)->insert($buffer);
                $imported += count($buffer);
            }
        });

        $this->info("Imported $imported rows into [$destinationTable].");
    }

    private function resolveInsertChunkSize(int $requestedChunkSize, int $columnCount): int
    {
        $requested = max(1, $requestedChunkSize);
        $optimizedChunk = max($requested, self::FAST_CHUNK_FLOOR);
        $columnsPerRow = $columnCount + 2;
        $maxSafeChunk = max(1, intdiv(self::MAX_BIND_PARAMS_PER_QUERY, max(1, $columnsPerRow)));

        return min($optimizedChunk, $maxSafeChunk);
    }

    /**
     * @param  array<int, string>  $sourceColumns
     * @return array<string, string>
     */
    private function buildColumnMap(array $sourceColumns): array
    {
        $map = [];
        $used = [];

        foreach ($sourceColumns as $sourceColumn) {
            $translated = Str::snake($this->translateColumnName($sourceColumn));

            if (isset($used[$translated])) {
                $used[$translated]++;
                $translated .= '_'.$used[$translated];
            } else {
                $used[$translated] = 1;
            }

            $map[$sourceColumn] = $translated;
        }

        return $map;
    }

    private function translateColumnName(string $sourceColumn): string
    {
        $normalized = Str::upper($this->normalizeTurkish($sourceColumn));

        $tokens = preg_split('/[^A-Z0-9]+/', $normalized) ?: [];

        $dictionary = [
            'PERSONEL' => 'personnel',
            'PER' => 'per',
            'AD' => 'name',
            'ADI' => 'name',
            'SOYAD' => 'surname',
            'SOYADI' => 'surname',
            'KODU' => 'code',
            'KOD' => 'code',
            'TARIH' => 'date',
            'GIRIS' => 'entrance',
            'CIKIS' => 'exit',
            'GEC' => 'late',
            'ERKEN' => 'early',
            'CALISMA' => 'work',
            'DEVAMSIZLIK' => 'absence',
            'RAPORU' => 'report',
        ];

        $translatedTokens = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $translatedTokens[] = $dictionary[$token] ?? strtolower($token);
        }

        return implode('_', $translatedTokens);
    }

    private function normalizeTurkish(string $value): string
    {
        return strtr($value, [
            'Ç' => 'C',
            'Ğ' => 'G',
            'İ' => 'I',
            'I' => 'I',
            'Ö' => 'O',
            'Ş' => 'S',
            'Ü' => 'U',
            'ç' => 'c',
            'ğ' => 'g',
            'ı' => 'i',
            'i' => 'i',
            'ö' => 'o',
            'ş' => 's',
            'ü' => 'u',
        ]);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureDestinationTable(string $destinationTable, array $columns): void
    {
        if (! Schema::hasTable($destinationTable)) {
            Schema::create($destinationTable, function (Blueprint $table) use ($columns): void {
                $table->bigIncrements('import_id');

                foreach ($columns as $column) {
                    $table->longText($column)->nullable();
                }

                $table->timestamps();
                $table->index('import_id');
            });

            return;
        }

        $existingColumns = Schema::getColumnListing($destinationTable);

        foreach ($columns as $column) {
            if (in_array($column, $existingColumns, true)) {
                continue;
            }

            Schema::table($destinationTable, function (Blueprint $table) use ($column): void {
                $table->longText($column)->nullable();
            });
        }
    }
}
