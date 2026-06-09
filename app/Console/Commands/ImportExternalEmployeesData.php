<?php

namespace App\Console\Commands;

use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportExternalEmployeesData extends Command
{
    private const PERSONNEL_IMAGES_TABLE = 'personnel_images';

    private const DEFAULT_SQLSERVER_SCHEMA = 'dbo';

    private const WIDE_TABLE_COLUMN_THRESHOLD = 150;

    private const FAST_CHUNK_FLOOR = 2000;

    private const MAX_BIND_PARAMS_PER_QUERY = 65000;

    private const PAYLOAD_CHUNK_CAP = 100;

    /**
     * @var array<string, bool>
     */
    private array $truncatedTables = [];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'external:import-employees
        {--host=192.168.1.158 : SQL Server host}
        {--port=1433 : SQL Server port}
        {--username=BiUser : SQL Server username}
        {--password= : SQL Server password}
            {--database=PrestoXL : SQL Server database name}
                {--schemas=D00001:Altona,D00002:Stern : Comma separated source-scope:alias pairs (scope can be schema or database/catalog)}
        {--tables= : Optional comma separated source table names (e.g. PERSONEL,PERSONEL_RESIMLERI)}
        {--chunk=500 : Insert chunk size}
        {--truncate : Truncate destination tables before import}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Turkish HR tables from external SQL Server into English-named local tables.';

    private const TABLE_MAP = [
        'PERSONEL' => 'personnel_employees',
        'FIRMA_PERSONELI' => 'company_personnel',
        'MAAS' => 'salary',
        'PERSONEL_UCRET_KESINTI' => 'personnel_salary_deductions',
        'PERSONEL_EKGELIR' => 'personnel_extra_income',
        'PERSONEL_EKKESINTI' => 'personnel_extra_deductions',
        'PERSONEL_IZIN' => 'personnel_leave',
        'PERSONEL_YILLIK_IZIN' => 'personnel_annual_leave',
        'PERSONEL_ODENEN_IZIN' => 'personnel_paid_leave',
        'PERSONEL_IZIN_SURE_CIKAR' => 'personnel_leave_duration_deduction',
        'YILLIK_IZIN_SABITLERI' => 'annual_leave_constants',
        'PERSONEL_SSK' => 'personnel_social_security_ssk',
        'PERSONEL_ISSIZLIK' => 'personnel_unemployment',
        'BAGKUR_KISI' => 'bagkur_person',
        'PERSONEL_VERGI_TANIMLARI' => 'personnel_tax_definitions',
        'PERSONEL_VERGI_INDIR_ARTTIR' => 'personnel_tax_reduction_increase',
        'PERSONEL_AYLIK_KESINTI' => 'personnel_monthly_deductions',
        'PERSONEL_SICIL' => 'personnel_registry_record',
        'PERSONEL_AKRABA' => 'personnel_relatives',
        'PERSONEL_OGRENIM' => 'personnel_education',
        'PERSONEL_RESIMLERI' => 'personnel_images',
        'PERSONEL_YETKILERI' => 'personnel_authorities_permissions',
        'PERSONEL_SINIFLARI' => 'personnel_classes_categories',
        'PERSONEL_OZELKOD' => 'personnel_special_code',
        'PERSONEL_FAZLA_MESAI' => 'personnel_overtime',
        'PERSONEL_BILDIRGE' => 'personnel_declaration',
        'PERSONEL_TASARRUF' => 'personnel_savings',
        'IK_PERSONEL' => 'hr_personnel',
        'IK_PERSONEL_IZIN' => 'hr_personnel_leave',
        'CRM_PERSONEL' => 'crm_personnel',
        'TECH_RDV_PERSONEL' => 'technical_appointment_personnel',
        'PERSONEL_ICRA' => 'personnel_enforcement_legal_action',
        'ISYERI_PERSONEL_BAG' => 'workplace_personnel_connection',
    ];

    private const TOKEN_MAP = [
        'AD' => 'name',
        'ADI' => 'name',
        'SOYAD' => 'surname',
        'BABA' => 'father',
        'ANA' => 'mother',
        'DOGUM' => 'birth',
        'YERI' => 'place',
        'TARIH' => 'date',
        'TC' => 'national_id',
        'KIMLIK' => 'identity',
        'NO' => 'number',
        'NUMARA' => 'number',
        'KOD' => 'code',
        'ACIKLAMA' => 'description',
        'PERSONEL' => 'personnel',
        'UCRET' => 'salary',
        'KESINTI' => 'deduction',
        'EK' => 'extra',
        'GELIR' => 'income',
        'IZIN' => 'leave',
        'YILLIK' => 'annual',
        'ODENEN' => 'paid',
        'SURE' => 'duration',
        'SABITLERI' => 'constants',
        'SSK' => 'ssk',
        'ISSIZLIK' => 'unemployment',
        'BAGKUR' => 'bagkur',
        'KISI' => 'person',
        'VERGI' => 'tax',
        'TANIMLARI' => 'definitions',
        'INDIR' => 'reduction',
        'ARTTIR' => 'increase',
        'AYLIK' => 'monthly',
        'SICIL' => 'registry',
        'AKRABA' => 'relative',
        'OGRENIM' => 'education',
        'RESIMLERI' => 'images',
        'YETKILERI' => 'authorities',
        'SINIFLARI' => 'classes',
        'OZEL' => 'special',
        'FAZLA' => 'overtime',
        'MESAI' => 'work',
        'BILDIRGE' => 'declaration',
        'TASARRUF' => 'savings',
        'ICRA' => 'enforcement',
        'ISYERI' => 'workplace',
        'BAG' => 'connection',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $password = (string) $this->option('password');

        if ($password === '') {
            $this->error('Missing --password option.');

            return self::FAILURE;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $this->truncatedTables = [];

        DB::connection()->disableQueryLog();

        $schemaAliases = $this->parseSchemaAliases((string) $this->option('schemas'));
        $connectionName = $this->configureExternalConnection((string) $this->option('database'));

        DB::connection($connectionName)->disableQueryLog();

        foreach ($schemaAliases as $schema => $alias) {
            $this->info("Importing from SQL Server schema [$schema] as [$alias]...");

            try {
                $this->importSchema($connectionName, $schema, $alias, $chunkSize);
            } catch (\Throwable $exception) {
                $this->error("Schema [$schema] failed: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        $this->info('External SQL Server import finished successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $schemaAliases
     */
    private function parseSchemaAliases(string $schemaAliases): array
    {
        $pairs = array_filter(array_map('trim', explode(',', $schemaAliases)));
        $result = [];

        foreach ($pairs as $pair) {
            [$db, $alias] = array_pad(explode(':', $pair, 2), 2, null);

            $database = trim((string) $db);
            $dbAlias = trim((string) ($alias ?? $db));

            if ($database === '' || $dbAlias === '') {
                continue;
            }

            $result[$database] = Str::snake(Str::lower($dbAlias));
        }

        return $result;
    }

    private function importSchema(string $connectionName, string $sourceScope, string $alias, int $chunkSize): void
    {
        $selectedTables = $this->selectedTables();
        $requiresPersonnelImport = $this->requiresPersonnelImportGuard($selectedTables);
        $personnelTableFound = false;
        $personnelRowsImported = 0;

        foreach (self::TABLE_MAP as $sourceTable => $englishTableName) {
            if ($selectedTables !== [] && ! in_array($sourceTable, $selectedTables, true)) {
                continue;
            }

            $sourceTableReference = $this->resolveSourceTableReference($connectionName, $sourceScope, $sourceTable);

            if ($sourceTableReference === null) {
                if ($sourceTable === 'PERSONEL') {
                    $personnelTableFound = false;
                }

                $this->warn("Skipping missing source table [$sourceScope.$sourceTable].");

                continue;
            }

            if ($sourceTable === 'PERSONEL') {
                $personnelTableFound = true;
            }

            $destinationTable = $this->destinationTableName($englishTableName);
            $columns = $this->loadSourceColumns(
                $connectionName,
                $sourceTableReference['catalog'],
                $sourceTableReference['schema'],
                $sourceTable
            );

            if ($columns === []) {
                $this->warn("Skipping [$sourceScope.$sourceTable] because no columns were discovered.");

                continue;
            }

            $columnMap = $this->buildColumnMap($columns);
            $usePayloadMode = $this->shouldUsePayloadMode($columnMap, $sourceTable);
            $insertChunkSize = $this->resolveInsertChunkSize($chunkSize, $usePayloadMode, count($columnMap));

            if ($sourceTable === 'PERSONEL_RESIMLERI') {
                $insertChunkSize = max(1, min($chunkSize, 50));
            }

            $this->ensureDestinationTable($destinationTable, array_values($columnMap), $usePayloadMode);

            if ((bool) $this->option('truncate') && ! isset($this->truncatedTables[$destinationTable])) {
                DB::table($destinationTable)->truncate();
                $this->truncatedTables[$destinationTable] = true;
            }

            $this->line(
                "Importing [{$sourceTableReference['qualified_table']}] ($alias) -> [$destinationTable]..."
            );

            $importedRows = $this->streamRowsIntoDestination(
                $connectionName,
                $sourceScope,
                $sourceTable,
                $destinationTable,
                $columnMap,
                $alias,
                $sourceTableReference,
                $usePayloadMode,
                $insertChunkSize
            );

            if ($sourceTable === 'PERSONEL') {
                $personnelRowsImported += $importedRows;
            }
        }

        if ($requiresPersonnelImport && (! $personnelTableFound || $personnelRowsImported === 0)) {
            throw new \RuntimeException(
                "Source scope [$sourceScope] imported zero rows from PERSONEL. "
                .'Import aborted to prevent partial employee data.'
            );
        }
    }

    /**
     * @param  array<int, string>  $selectedTables
     */
    private function requiresPersonnelImportGuard(array $selectedTables): bool
    {
        return $selectedTables === [] || in_array('PERSONEL', $selectedTables, true);
    }

    /**
     * @return array<int, string>
     */
    private function selectedTables(): array
    {
        $tablesOption = trim((string) $this->option('tables'));

        if ($tablesOption === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $table): string => Str::upper(trim($table)),
            explode(',', $tablesOption)
        )));
    }

    /**
     * @param  array<string, string>  $columnMap
     */
    private function shouldUsePayloadMode(array $columnMap, string $sourceTable): bool
    {
        return count($columnMap) > self::WIDE_TABLE_COLUMN_THRESHOLD;
    }

    private function resolveInsertChunkSize(int $chunkSize, bool $usePayloadMode, int $columnCount): int
    {
        if ($usePayloadMode) {
            return min($chunkSize, self::PAYLOAD_CHUNK_CAP);
        }

        $optimizedChunkSize = max($chunkSize, self::FAST_CHUNK_FLOOR);

        // Reserve parameters for metadata/timestamps added to each inserted row.
        $columnsPerRow = $columnCount + 5;
        $maxSafeChunkSize = max(1, intdiv(self::MAX_BIND_PARAMS_PER_QUERY, max(1, $columnsPerRow)));

        return min($optimizedChunkSize, $maxSafeChunkSize);
    }

    private function destinationTableName(string $englishTableName): string
    {
        return Str::snake($englishTableName);
    }

    private function configureExternalConnection(string $databaseName): string
    {
        $connectionName = 'external_sqlsrv_import';

        config([
            'database.connections.'.$connectionName => [
                'driver' => 'sqlsrv',
                'host' => (string) $this->option('host'),
                'port' => (string) $this->option('port'),
                'database' => $databaseName,
                'username' => (string) $this->option('username'),
                'password' => (string) $this->option('password'),
                'charset' => 'utf8',
                'prefix' => '',
                'prefix_indexes' => true,
                'encrypt' => 'false',
                'trust_server_certificate' => 'true',
            ],
        ]);

        DB::purge($connectionName);

        return $connectionName;
    }

    /**
     * @return array{catalog: string, schema: string, qualified_table: string}|null
     */
    private function resolveSourceTableReference(string $connectionName, string $sourceScope, string $sourceTable): ?array
    {
        $tableMetadataRows = DB::connection($connectionName)
            ->table('INFORMATION_SCHEMA.TABLES')
            ->select('TABLE_CATALOG', 'TABLE_SCHEMA')
            ->where('TABLE_NAME', $sourceTable)
            ->where(function ($query) use ($sourceScope): void {
                $query
                    ->where('TABLE_SCHEMA', $sourceScope)
                    ->orWhere('TABLE_CATALOG', $sourceScope);
            })
            ->get();

        if ($tableMetadataRows->isEmpty()) {
            return null;
        }

        $sortedRows = $tableMetadataRows
            ->sortBy(function ($row) use ($sourceScope): array {
                $rowSchema = (string) ($row->TABLE_SCHEMA ?? '');
                $rowCatalog = (string) ($row->TABLE_CATALOG ?? '');

                if ($rowSchema === $sourceScope) {
                    return [0, $rowCatalog, $rowSchema];
                }

                if (
                    $rowCatalog === $sourceScope
                    && Str::lower($rowSchema) === self::DEFAULT_SQLSERVER_SCHEMA
                ) {
                    return [1, $rowCatalog, $rowSchema];
                }

                return [2, $rowCatalog, $rowSchema];
            })
            ->values();

        $resolved = $sortedRows->first();
        $catalog = (string) ($resolved->TABLE_CATALOG ?? '');
        $schema = (string) ($resolved->TABLE_SCHEMA ?? '');

        return [
            'catalog' => $catalog,
            'schema' => $schema,
            'qualified_table' => $this->buildQualifiedSourceTableName($catalog, $schema, $sourceTable),
        ];
    }

    private function buildQualifiedSourceTableName(string $catalog, string $schema, string $table): string
    {
        $safeCatalog = str_replace(']', ']]', $catalog);
        $safeSchema = str_replace(']', ']]', $schema);
        $safeTable = str_replace(']', ']]', $table);

        return "[$safeCatalog].[$safeSchema].[$safeTable]";
    }

    /**
     * @return array<int, string>
     */
    private function loadSourceColumns(string $connectionName, string $catalog, string $schema, string $sourceTable): array
    {
        return DB::connection($connectionName)
            ->table('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_NAME', $sourceTable)
            ->where('TABLE_CATALOG', $catalog)
            ->where('TABLE_SCHEMA', $schema)
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')
            ->map(static fn ($column) => (string) $column)
            ->toArray();
    }

    /**
     * @param  array<int, string>  $sourceColumns
     * @return array<string, string>
     */
    private function buildColumnMap(array $sourceColumns): array
    {
        $map = [];
        $used = [];

        foreach ($sourceColumns as $column) {
            $translated = $this->translateColumnName($column);

            if (isset($used[$translated])) {
                $used[$translated]++;
                $translated .= '_'.$used[$translated];
            } else {
                $used[$translated] = 1;
            }

            $map[$column] = $translated;
        }

        return $map;
    }

    private function translateColumnName(string $sourceColumn): string
    {
        $normalized = Str::upper($this->normalizeTurkish($sourceColumn));
        $tokens = preg_split('/[^A-Z0-9]+/', $normalized) ?: [];
        $englishTokens = [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $englishTokens[] = self::TOKEN_MAP[$token] ?? Str::lower($token);
        }

        if ($englishTokens === []) {
            return 'column_'.Str::lower(Str::random(6));
        }

        return Str::snake(implode('_', $englishTokens));
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
     * @param  array<int, string>  $translatedColumns
     */
    private function ensureDestinationTable(string $destinationTable, array $translatedColumns, bool $usePayloadMode): void
    {
        if ($destinationTable === self::PERSONNEL_IMAGES_TABLE) {
            $this->ensurePersonnelImagesTableStructure();

            return;
        }

        $requiredColumns = $usePayloadMode
            ? ['row_payload', 'company', 'source_schema', 'source_table']
            : array_values(array_unique(array_merge(
                $translatedColumns,
                ['company', 'source_schema', 'source_table']
            )));

        if (! Schema::hasTable($destinationTable)) {
            Schema::create($destinationTable, function (Blueprint $table) use ($requiredColumns): void {
                $table->bigIncrements('import_id');

                foreach ($requiredColumns as $columnName) {
                    $table->longText($columnName)->nullable();
                }

                $table->timestamps();
            });

            return;
        }

        $existing = Schema::getColumnListing($destinationTable);

        foreach ($requiredColumns as $columnName) {
            if (in_array($columnName, $existing, true)) {
                continue;
            }

            Schema::table($destinationTable, function (Blueprint $table) use ($columnName): void {
                $table->longText($columnName)->nullable();
            });
        }
    }

    private function ensurePersonnelImagesTableStructure(): void
    {
        if (! Schema::hasTable(self::PERSONNEL_IMAGES_TABLE)) {
            Schema::create(self::PERSONNEL_IMAGES_TABLE, function (Blueprint $table): void {
                $table->bigIncrements('import_id');
                $table->integer('personnel_id')->nullable();
                $table->longBlob('resim')->nullable();
                $table->longText('company')->nullable();
                $table->longText('source_schema')->nullable();
                $table->longText('source_table')->nullable();
                $table->timestamps();
            });

            return;
        }

        $existing = Schema::getColumnListing(self::PERSONNEL_IMAGES_TABLE);

        if (! in_array('personnel_id', $existing, true)) {
            Schema::table(self::PERSONNEL_IMAGES_TABLE, function (Blueprint $table): void {
                $table->integer('personnel_id')->nullable();
            });
        }

        if (! in_array('resim', $existing, true)) {
            Schema::table(self::PERSONNEL_IMAGES_TABLE, function (Blueprint $table): void {
                $table->longBlob('resim')->nullable();
            });
        }

        foreach (['company', 'source_schema', 'source_table'] as $metadataColumn) {
            if (in_array($metadataColumn, $existing, true)) {
                continue;
            }

            Schema::table(self::PERSONNEL_IMAGES_TABLE, function (Blueprint $table) use ($metadataColumn): void {
                $table->longText($metadataColumn)->nullable();
            });
        }

        DB::statement('ALTER TABLE `'.self::PERSONNEL_IMAGES_TABLE.'` MODIFY COLUMN `personnel_id` INT NULL');
        DB::statement('ALTER TABLE `'.self::PERSONNEL_IMAGES_TABLE.'` MODIFY COLUMN `resim` LONGBLOB NULL');

        $this->migratePersonnelImagesPayloadIfNeeded();
    }

    private function migratePersonnelImagesPayloadIfNeeded(): void
    {
        if (! Schema::hasColumn(self::PERSONNEL_IMAGES_TABLE, 'row_payload')) {
            return;
        }

        $rowsWithPayload = DB::table(self::PERSONNEL_IMAGES_TABLE)
            ->whereNotNull('row_payload')
            ->count();

        if ($rowsWithPayload > 0) {
            DB::table(self::PERSONNEL_IMAGES_TABLE)
                ->whereNotNull('row_payload')
                ->orderBy('import_id')
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $payload = json_decode((string) $row->row_payload, true);

                        if (! is_array($payload)) {
                            continue;
                        }

                        $personnelId = isset($payload['personnel_id'])
                            ? (int) $payload['personnel_id']
                            : null;

                        $resim = $payload['resim'] ?? null;

                        if (is_string($resim) && Str::startsWith($resim, 'base64:')) {
                            $resim = base64_decode(Str::after($resim, 'base64:'), true);
                        }

                        DB::table(self::PERSONNEL_IMAGES_TABLE)
                            ->where('import_id', $row->import_id)
                            ->update([
                                'personnel_id' => $personnelId,
                                'resim' => $resim,
                            ]);
                    }
                }, 'import_id');
        }

        Schema::table(self::PERSONNEL_IMAGES_TABLE, function (Blueprint $table): void {
            $table->dropColumn('row_payload');
        });
    }

    /**
     * @param  array<string, string>  $columnMap
     */
    private function streamRowsIntoDestination(
        string $connectionName,
        string $sourceScope,
        string $sourceTable,
        string $destinationTable,
        array $columnMap,
        string $company,
        array $sourceTableReference,
        bool $usePayloadMode,
        int $chunkSize
    ): int {
        if ($sourceTable === 'PERSONEL_RESIMLERI') {
            return $this->streamPersonnelImagesIntoDestination(
                $connectionName,
                $sourceScope,
                $sourceTable,
                $destinationTable,
                $company,
                $sourceTableReference,
                $chunkSize
            );
        }

        $buffer = [];
        $imported = 0;
        $timestamp = now();

        $sourceSelectSql = 'SELECT * FROM '.$sourceTableReference['qualified_table'];

        foreach (DB::connection($connectionName)->cursor($sourceSelectSql) as $row) {
            $buffer[] = $this->transformRow(
                $row,
                $columnMap,
                $company,
                $sourceScope,
                $sourceTable,
                $usePayloadMode,
                $timestamp
            );

            if (count($buffer) < $chunkSize) {
                continue;
            }

            DB::table($destinationTable)->insert($buffer);
            $imported += count($buffer);
            $buffer = [];
        }

        if ($buffer !== []) {
            DB::table($destinationTable)->insert($buffer);
            $imported += count($buffer);
        }

        $this->info("Imported $imported rows into [$destinationTable].");

        return $imported;
    }

    private function streamPersonnelImagesIntoDestination(
        string $connectionName,
        string $sourceScope,
        string $sourceTable,
        string $destinationTable,
        string $company,
        array $sourceTableReference,
        int $chunkSize
    ): int {
        $offset = 0;
        $imported = 0;

        while (true) {
            $rows = DB::connection($connectionName)->select(
                'SELECT PERSONEL_ID, CAST(RESIM AS VARBINARY(MAX)) AS RESIM '
                .'FROM '.$sourceTableReference['qualified_table'].' '
                .'ORDER BY PERSONEL_ID '
                .'OFFSET ? ROWS FETCH NEXT ? ROWS ONLY',
                [$offset, $chunkSize]
            );

            if ($rows === []) {
                break;
            }

            $timestamp = now();
            $buffer = [];

            foreach ($rows as $row) {
                $resim = null;

                if (isset($row->RESIM)) {
                    $rawResim = $row->RESIM;

                    if (is_resource($rawResim)) {
                        $rawResim = stream_get_contents($rawResim);
                    }

                    if (is_string($rawResim) && $rawResim !== '') {
                        $resim = $rawResim;
                    }
                }

                $buffer[] = [
                    'personnel_id' => isset($row->PERSONEL_ID) ? (int) $row->PERSONEL_ID : null,
                    'resim' => $resim,
                    'company' => $company,
                    'source_schema' => $sourceScope,
                    'source_table' => $sourceTable,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            if ($buffer !== []) {
                DB::table($destinationTable)->insert($buffer);
                $imported += count($buffer);
            }

            $offset += count($rows);
        }

        $this->info("Imported $imported rows into [$destinationTable].");

        return $imported;
    }

    /**
     * @param  array<string, string>  $columnMap
     * @return array<string, mixed>
     */
    private function transformRow(
        object $row,
        array $columnMap,
        string $company,
        string $sourceSchema,
        string $sourceTable,
        bool $usePayloadMode,
        string $timestamp
    ): array {
        $record = $usePayloadMode ? ['row_payload' => ''] : [];
        $payload = [];

        foreach ($columnMap as $sourceColumn => $destinationColumn) {
            $value = $row->{$sourceColumn} ?? null;

            if (is_resource($value)) {
                $value = stream_get_contents($value);
            }

            if ($value instanceof DateTimeInterface) {
                $normalizedValue = $value->format('Y-m-d H:i:s.u');

                if ($usePayloadMode) {
                    $payload[$destinationColumn] = $normalizedValue;
                } else {
                    $record[$destinationColumn] = $normalizedValue;
                }

                continue;
            }

            if (is_bool($value)) {
                $normalizedValue = $value ? '1' : '0';

                if ($usePayloadMode) {
                    $payload[$destinationColumn] = $normalizedValue;
                } else {
                    $record[$destinationColumn] = $normalizedValue;
                }

                continue;
            }

            if ($usePayloadMode) {
                $payload[$destinationColumn] = $this->normalizePayloadValue($value);
            } else {
                $record[$destinationColumn] = $value;
            }
        }

        if ($usePayloadMode) {
            $record['row_payload'] = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            ) ?: '{}';
        }

        $record['company'] = $company;
        $record['source_schema'] = $sourceSchema;
        $record['source_table'] = $sourceTable;

        $record['created_at'] = $timestamp;
        $record['updated_at'] = $timestamp;

        return $record;
    }

    private function normalizePayloadValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return 'base64:'.base64_encode($value);
    }
}
