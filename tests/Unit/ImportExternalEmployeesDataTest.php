<?php

namespace Tests\Unit;

use App\Console\Commands\ImportExternalEmployeesData;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ImportExternalEmployeesDataTest extends TestCase
{
    public function test_table_map_contains_requested_prestoxl_tables(): void
    {
        $reflection = new ReflectionClass(ImportExternalEmployeesData::class);
        $tableMap = $reflection->getConstant('TABLE_MAP');

        $expectedSourceTables = [
            'PERSONEL',
            'FIRMA_PERSONELI',
            'MAAS',
            'PERSONEL_IZIN',
            'PERSONEL_OGRENIM',
            'PERSONEL_RESIMLERI',
            'PERSONEL_AKRABA',
            'PERSONEL_AYLIK_KESINTI',
            'PERSONEL_SSK',
            'PERSONEL_ISSIZLIK',
            'PERSONEL_VERGI_TANIMLARI',
            'PERSONEL_UCRET_KESINTI',
            'PERSONEL_EKGELIR',
            'PERSONEL_EKKESINTI',
            'PERSONEL_YILLIK_IZIN',
            'PERSONEL_SICIL',
            'PERSONEL_FAZLA_MESAI',
            'PERSONEL_TASARRUF',
            'IK_PERSONEL',
            'IK_PERSONEL_IZIN',
            'CRM_PERSONEL',
            'TECH_RDV_PERSONEL',
        ];

        foreach ($expectedSourceTables as $sourceTable) {
            $this->assertArrayHasKey($sourceTable, $tableMap);
        }
    }

    public function test_parse_schema_aliases_returns_expected_company_map(): void
    {
        $command = new ImportExternalEmployeesData;
        $reflection = new ReflectionClass(ImportExternalEmployeesData::class);
        $method = $reflection->getMethod('parseSchemaAliases');

        $aliases = $method->invoke($command, 'D00001:Stern,D00002:Altona');

        $this->assertSame([
            'D00001' => 'stern',
            'D00002' => 'altona',
        ], $aliases);
    }

    public function test_destination_table_name_is_not_company_prefixed(): void
    {
        $command = new ImportExternalEmployeesData;
        $reflection = new ReflectionClass(ImportExternalEmployeesData::class);
        $method = $reflection->getMethod('destinationTableName');

        $destinationTableName = $method->invoke($command, 'personnel_employees');

        $this->assertSame('personnel_employees', $destinationTableName);
    }

    public function test_should_use_payload_mode_for_wide_tables_only(): void
    {
        $command = new ImportExternalEmployeesData;
        $reflection = new ReflectionClass(ImportExternalEmployeesData::class);
        $method = $reflection->getMethod('shouldUsePayloadMode');

        $narrowColumnMap = [];
        for ($index = 0; $index < 150; $index++) {
            $narrowColumnMap['c_'.$index] = 'col_'.$index;
        }

        $wideColumnMap = $narrowColumnMap;
        $wideColumnMap['c_151'] = 'col_151';

        $this->assertFalse($method->invoke($command, $narrowColumnMap, 'PERSONEL'));
        $this->assertTrue($method->invoke($command, $wideColumnMap, 'PERSONEL'));
        $this->assertFalse($method->invoke($command, $narrowColumnMap, 'PERSONEL_RESIMLERI'));
    }
}
