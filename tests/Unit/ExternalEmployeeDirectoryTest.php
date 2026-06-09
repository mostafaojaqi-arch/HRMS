<?php

namespace Tests\Unit;

use App\Helpers\ExternalEmployeeDirectory;
use PHPUnit\Framework\TestCase;

class ExternalEmployeeDirectoryTest extends TestCase
{
    public function test_it_resolves_join_columns_from_available_schema(): void
    {
        $directory = new ExternalEmployeeDirectory;

        $pair = $directory->resolveJoinColumnPair(
            ['id', 'first_name', 'last_name'],
            ['employee_id', 'profile_photo_path']
        );

        $this->assertSame([
            'employee' => 'id',
            'image' => 'employee_id',
        ], $pair);
    }

    public function test_it_builds_display_values_from_common_employee_columns(): void
    {
        $directory = new ExternalEmployeeDirectory;
        $employee = (object) [
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'mobile_number' => '988877666',
            'is_active' => '1',
            'id' => 15,
        ];

        $this->assertSame('Sara Ali', $directory->resolveDisplayName($employee, ['id', 'first_name', 'last_name']));
        $this->assertSame('988877666', $directory->resolveMobileNumber($employee, ['mobile_number']));
        $this->assertTrue($directory->resolveIsActive($employee, ['is_active']));
        $this->assertSame('15', $directory->resolveIdentifier($employee, ['id']));

        $importedEmployee = (object) [
            'personnel_name' => 'ALI',
            'personnel_soyadi' => 'KAYA',
            'personnel_id' => '21',
        ];

        $this->assertSame(
            'ALI KAYA',
            $directory->resolveDisplayName($importedEmployee, ['personnel_name', 'personnel_soyadi', 'personnel_id'])
        );
    }

    public function test_it_resolves_search_columns_and_photo_fallbacks(): void
    {
        $directory = new ExternalEmployeeDirectory;

        $this->assertSame(
            ['id', 'name', 'mobile_number'],
            $directory->resolveSearchColumns(['id', 'name', 'mobile_number', 'created_at'])
        );

        $this->assertSame(
            'https://example.test/photo.jpg',
            $directory->resolvePhotoPath(
                (object) ['profile_photo_path' => null],
                (object) ['photo_path' => 'https://example.test/photo.jpg'],
                ['profile_photo_path'],
                ['photo_path']
            )
        );

        $this->assertSame(
            'data:image/jpeg;base64,Zm9vYmFy',
            $directory->resolvePhotoPath(
                (object) ['personnel_id' => '1'],
                (object) ['row_payload' => '{"resim":"base64:Zm9vYmFy"}'],
                ['personnel_id'],
                ['row_payload']
            )
        );

        $this->assertSame(
            'data:image/jpeg;base64,Zm9vYmFy',
            $directory->resolvePhotoPath(
                (object) ['personnel_id' => '1'],
                (object) ['row_payload' => '{"resim":"Zm9vYmFy"}'],
                ['personnel_id'],
                ['row_payload']
            )
        );

        $binaryJpeg = "\xFF\xD8\xFF\xE0".'JFIF'."\x00\x01";
        $binaryImageUrl = $directory->resolvePhotoPath(
            (object) ['personnel_id' => '1'],
            (object) ['resim' => $binaryJpeg],
            ['personnel_id'],
            ['resim']
        );

        $this->assertStringStartsWith('data:image/jpeg;base64,', $binaryImageUrl);
    }

    public function test_it_resolves_sort_column_safely_for_external_tables(): void
    {
        $directory = new ExternalEmployeeDirectory;

        $this->assertSame('import_id', $directory->resolveSortColumn(['import_id', 'name']));
        $this->assertSame('name', $directory->resolveSortColumn(['name', 'created_at']));
        $this->assertNull($directory->resolveSortColumn([]));
    }

    public function test_it_resolves_requested_fields_with_candidate_fallbacks(): void
    {
        $directory = new ExternalEmployeeDirectory;
        $employee = (object) [
            'personnel_id' => '101',
            'personnel_kodu' => 'EMP-101',
            'company' => 'stern',
            'ssk_statusu' => '1',
        ];

        $columns = ['personnel_id', 'personnel_kodu', 'company', 'ssk_statusu'];

        $this->assertSame('101', $directory->resolveFieldValue($employee, $columns, ['personel_id', 'personnel_id']));
        $this->assertSame('EMP-101', $directory->resolveFieldValue($employee, $columns, ['personel_kodu', 'personnel_kodu']));
        $this->assertSame('stern', $directory->resolveFieldValue($employee, $columns, ['company']));
        $this->assertSame('1', $directory->resolveFieldValue($employee, $columns, ['ssk_statusu']));
        $this->assertSame('Active', $directory->resolveStatusLabel('1'));
        $this->assertSame('Passive', $directory->resolveStatusLabel('0'));
        $this->assertSame('2', $directory->resolveStatusLabel('2'));
        $this->assertSame('---', $directory->resolveStatusLabel(''));
        $this->assertSame(
            '88',
            $directory->resolvePayloadFieldValue('{"personnel_id":"88","resim":"base64:Zm9v"}', ['personnel_id'])
        );
        $this->assertSame('', $directory->resolvePayloadFieldValue(null, ['personnel_id']));
    }
}
