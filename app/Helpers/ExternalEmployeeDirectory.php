<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExternalEmployeeDirectory
{
    private const DEFAULT_PHOTO_PATH = 'profile-photos/.default-photo.jpg';

    private const SEARCH_COLUMN_CANDIDATES = [
        'import_id',
        'id',
        'employee_id',
        'personnel_id',
        'personel_id',
        'sicil_no',
        'personnel_kodu',
        'personel_kodu',
        'company',
        'ssk_statusu',
        'personnel_name',
        'personnel_soyadi',
        'per_name_soyadi',
        'first_name',
        'last_name',
        'name',
        'surname',
        'full_name',
        'mobile_number',
        'mobile',
        'national_number',
        'identity_number',
    ];

    private const DISPLAY_NAME_CANDIDATES = [
        ['personnel_name', 'personnel_soyadi'],
        ['first_name', 'last_name'],
        ['name', 'surname'],
        ['first_name', 'father_name', 'last_name'],
        ['name', 'middle_name', 'surname'],
    ];

    private const MOBILE_COLUMN_CANDIDATES = [
        'mobile_number',
        'mobile',
        'phone_number',
        'phone',
    ];

    private const ACTIVE_COLUMN_CANDIDATES = [
        'is_active',
        'active',
        'status',
    ];

    private const ID_COLUMN_CANDIDATES = [
        'import_id',
        'id',
        'employee_id',
        'personnel_id',
        'sicil_no',
    ];

    private const JOIN_COLUMN_PAIRS = [
        ['employee_id', 'employee_id'],
        ['personnel_id', 'personnel_id'],
        ['employee_id', 'personnel_id'],
        ['id', 'employee_id'],
        ['id', 'personnel_id'],
        ['id', 'id'],
        ['sicil_no', 'sicil_no'],
    ];

    private const PHOTO_COLUMN_CANDIDATES = [
        'row_payload',
        'profile_photo_path',
        'photo_path',
        'image_path',
        'path',
        'photo',
        'image',
        'resim',
    ];

    /**
     * @param  array<int, string>  $availableColumns
     * @return array<int, string>
     */
    public function resolveSearchColumns(array $availableColumns): array
    {
        return array_values(array_intersect(self::SEARCH_COLUMN_CANDIDATES, $availableColumns));
    }

    /**
     * @param  array<int, string>  $employeeColumns
     * @param  array<int, string>  $imageColumns
     * @return array{employee: string, image: string}|null
     */
    public function resolveJoinColumnPair(array $employeeColumns, array $imageColumns): ?array
    {
        foreach (self::JOIN_COLUMN_PAIRS as [$employeeColumn, $imageColumn]) {
            if (! in_array($employeeColumn, $employeeColumns, true)) {
                continue;
            }

            if (! in_array($imageColumn, $imageColumns, true)) {
                continue;
            }

            return [
                'employee' => $employeeColumn,
                'image' => $imageColumn,
            ];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    public function resolveSortColumn(array $availableColumns): ?string
    {
        foreach (self::ID_COLUMN_CANDIDATES as $candidate) {
            if (in_array($candidate, $availableColumns, true)) {
                return $candidate;
            }
        }

        return $availableColumns[0] ?? null;
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    public function resolveDisplayName(object $employee, array $availableColumns): string
    {
        foreach (self::DISPLAY_NAME_CANDIDATES as $parts) {
            $values = [];

            foreach ($parts as $part) {
                if (! in_array($part, $availableColumns, true)) {
                    $values = [];
                    break;
                }

                $value = trim((string) data_get($employee, $part));

                if ($value === '') {
                    $values = [];
                    break;
                }

                $values[] = $value;
            }

            if ($values !== []) {
                return trim(implode(' ', $values));
            }
        }

        foreach (['per_name_soyadi', 'full_name', 'name'] as $candidate) {
            if (! in_array($candidate, $availableColumns, true)) {
                continue;
            }

            $value = trim((string) data_get($employee, $candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return (string) data_get($employee, $this->resolveIdentifier($employee, $availableColumns));
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    public function resolveMobileNumber(object $employee, array $availableColumns): ?string
    {
        $value = $this->resolveFirstAvailableValue($employee, $availableColumns, self::MOBILE_COLUMN_CANDIDATES);

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    public function resolveIsActive(object $employee, array $availableColumns): bool
    {
        $value = $this->resolveFirstAvailableValue($employee, $availableColumns, self::ACTIVE_COLUMN_CANDIDATES);

        if ($value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? ((int) $value === 1);
    }

    /**
     * @param  array<int, string>  $availableColumns
     */
    public function resolveIdentifier(object $employee, array $availableColumns): string
    {
        $value = $this->resolveFirstAvailableValue($employee, $availableColumns, self::ID_COLUMN_CANDIDATES);

        return $value !== '' ? $value : '';
    }

    /**
     * @param  array<int, string>  $availableColumns
     * @param  array<int, string>  $candidates
     */
    public function resolveFieldValue(object $record, array $availableColumns, array $candidates): string
    {
        return $this->resolveFirstAvailableValue($record, $availableColumns, $candidates);
    }

    public function resolveStatusLabel(string $rawStatus): string
    {
        $normalizedStatus = trim($rawStatus);

        if ($normalizedStatus === '') {
            return '---';
        }

        return match ($normalizedStatus) {
            '1' => 'Active',
            '0' => 'Passive',
            default => $normalizedStatus,
        };
    }

    /**
     * @param  array<int, string>  $candidates
     */
    public function resolvePayloadFieldValue(?string $rowPayload, array $candidates): string
    {
        if ($rowPayload === null || trim($rowPayload) === '') {
            return '';
        }

        $payload = json_decode($rowPayload, true);

        if (! is_array($payload)) {
            return '';
        }

        foreach ($candidates as $candidate) {
            $value = $payload[$candidate] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @param  array<int, string>  $employeeColumns
     * @param  array<int, string>  $imageColumns
     */
    public function resolvePhotoPath(object $employee, ?object $image, array $employeeColumns, array $imageColumns): string
    {
        $path = $this->resolveFirstAvailableValue($image ?? $employee, $image ? $imageColumns : $employeeColumns, self::PHOTO_COLUMN_CANDIDATES);

        if ($path === '') {
            $path = $this->resolveFirstAvailableValue($employee, $employeeColumns, self::PHOTO_COLUMN_CANDIDATES);
        }

        if ($path === '') {
            return Storage::disk('public')->url(self::DEFAULT_PHOTO_PATH);
        }

        $base64Photo = $this->normalizeBase64Photo($path);

        if ($base64Photo !== null) {
            return $base64Photo;
        }

        $binaryPhoto = $this->normalizeBinaryPhoto($path);

        if ($binaryPhoto !== null) {
            return $binaryPhoto;
        }

        if (Str::startsWith($path, '{')) {
            $payload = json_decode($path, true);

            if (is_array($payload)) {
                foreach (['resim', 'image', 'photo', 'image_path', 'photo_path'] as $payloadKey) {
                    $payloadValue = $payload[$payloadKey] ?? null;

                    if (! is_string($payloadValue) || $payloadValue === '') {
                        continue;
                    }

                    $base64Photo = $this->normalizeBase64Photo($payloadValue);

                    if ($base64Photo !== null) {
                        return $base64Photo;
                    }

                    $path = $payloadValue;
                    break;
                }
            }
        }

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        if (Str::startsWith($path, '/')) {
            return $path;
        }

        if (Str::startsWith($path, 'storage/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    private function normalizeBase64Photo(string $value): ?string
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            return null;
        }

        if (Str::startsWith($normalizedValue, 'base64:')) {
            return 'data:image/jpeg;base64,'.substr($normalizedValue, 7);
        }

        if (Str::startsWith($normalizedValue, 'data:image/')) {
            return $normalizedValue;
        }

        if (! preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $normalizedValue)) {
            return null;
        }

        $decodedValue = base64_decode($normalizedValue, true);

        if ($decodedValue === false || $decodedValue === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.$normalizedValue;
    }

    private function normalizeBinaryPhoto(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $binaryHeader = substr($value, 0, 4);

        if (str_starts_with($binaryHeader, "\xFF\xD8\xFF")) {
            return 'data:image/jpeg;base64,'.base64_encode($value);
        }

        if ($binaryHeader === "\x89PNG") {
            return 'data:image/png;base64,'.base64_encode($value);
        }

        return null;
    }

    /**
     * @param  array<int, string>  $availableColumns
     * @param  array<int, string>  $candidates
     */
    private function resolveFirstAvailableValue(object $record, array $availableColumns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (! in_array($candidate, $availableColumns, true)) {
                continue;
            }

            $value = trim((string) data_get($record, $candidate));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
