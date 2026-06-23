<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimeSheetReport
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function report(array $filters = []): array
    {
        $dateFrom = (string) ($filters['date_from'] ?? now()->startOfMonth()->toDateString());
        $dateTo = (string) ($filters['date_to'] ?? now()->endOfMonth()->toDateString());
        $searchTerm = trim((string) ($filters['search_term'] ?? ''));
        $perPage = max(1, (int) ($filters['per_page'] ?? 31));

        $queryContext = $this->buildBaseQuery($dateFrom, $dateTo, $searchTerm);
        $dailyRows = $queryContext['daily']->paginate($perPage);
        $summaryRows = (clone $queryContext['summary'])->limit(10000)->get();

        $normalizedDaily = $dailyRows->getCollection()->map(fn (object $row): array => $this->normalizeRow($row));
        $dailyRows->setCollection($normalizedDaily);

        $normalizedSummaryRows = $summaryRows->map(fn (object $row): array => $this->normalizeRow($row));

        return [
            'daily_rows' => $dailyRows,
            'summary_cards' => $this->buildSummaryCards($normalizedSummaryRows),
            'monthly_summary' => $this->buildMonthlySummary($normalizedSummaryRows),
        ];
    }

    public function emptyPaginator(int $perPage = 31): LengthAwarePaginator
    {
        return new Paginator(
            collect(),
            0,
            $perPage,
            1,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * @return array{daily: Builder, summary: Builder}
     */
    private function buildBaseQuery(string $dateFrom, string $dateTo, string $searchTerm): array
    {
        $reportTable = DB::getSchemaBuilder()->hasTable('personnel_time_reports')
            ? 'personnel_time_reports'
            : 'gc_raporu';
        $mappedPersonnelTable = 'prestoXL_employees';
        $fallbackPersonnelTable = 'personnel_employees';

        if (! DB::getSchemaBuilder()->hasTable($reportTable)) {
            throw new \RuntimeException('Local table [personnel_time_reports] does not exist. Run import command first.');
        }

        $hasMappedTable = DB::getSchemaBuilder()->hasTable($mappedPersonnelTable)
            && DB::getSchemaBuilder()->hasColumn($mappedPersonnelTable, 'speedup_personelid')
            && DB::getSchemaBuilder()->hasColumn($mappedPersonnelTable, 'speedup_kurumkodu');

        $useMappedPersonnel = $hasMappedTable
            && DB::table($mappedPersonnelTable)
                ->whereNotNull('speedup_personelid')
                ->where('speedup_personelid', '!=', '')
                ->exists();

        $personnelTable = $useMappedPersonnel ? $mappedPersonnelTable : $fallbackPersonnelTable;

        if (! DB::getSchemaBuilder()->hasTable($personnelTable)) {
            throw new \RuntimeException('Local table [personnel_employees] does not exist. Run import command first.');
        }

        $reportColumns = DB::getSchemaBuilder()->getColumnListing($reportTable);
        $personnelColumns = DB::getSchemaBuilder()->getColumnListing($personnelTable);

        $reportEmployeeIdColumn = $this->resolveColumn($reportColumns, [
            'personelid', 'personel_id', 'personnel_id', 'employee_id', 'sicilno', 'sicil_no', 'per_id',
        ]);

        $personnelEmployeeIdColumn = $useMappedPersonnel
            ? $this->resolveColumn($personnelColumns, ['speedup_personelid'])
            : $this->resolveColumn($personnelColumns, [
                'personelid', 'personel_id', 'personnel_id', 'employee_id', 'sicilno', 'sicil_no', 'per_id',
            ]);

        $reportCompanyColumn = $this->resolveColumn($reportColumns, ['company', 'company_nme', 'company_name']);
        $personnelCompanyColumn = $useMappedPersonnel
            ? $this->resolveColumn($personnelColumns, ['speedup_kurumkodu'])
            : $this->resolveColumn($personnelColumns, ['company', 'company_nme', 'company_name', 'kurumkodu']);

        $personnelDisplayEmployeeIdColumn = $useMappedPersonnel
            ? $this->resolveColumn($personnelColumns, ['per_id', 'personnel_code'])
            : null;

        if ($reportCompanyColumn === null) {
            $reportCompanyColumn = $this->resolveColumn($reportColumns, ['kurumkodu', 'company_code']);
        }

        $reportDateColumn = $this->resolveColumn($reportColumns, [
            'tarih', 'date', 'gun', 'islem_tarih', 'islem_tarihi', 'attendance_date', 'harekettarihi',
        ]);

        $checkInColumn = $this->resolveColumn($reportColumns, [
            'giris', 'giris_saati', 'girissaati', 'ilk_giris', 'check_in', 'checkin', 'entrance', 'igiris',
        ]);

        $checkOutColumn = $this->resolveColumn($reportColumns, [
            'cikis', 'cikis_saati', 'cikissaati', 'son_cikis', 'check_out', 'checkout', 'exit', 'icikis',
        ]);

        $workedMinutesColumn = $this->resolveColumn($reportColumns, [
            'calisma_dakika', 'calisma_suresi_dakika', 'toplam_calisma_dakika', 'work_minutes', 'imeccalismasuresi', 'mec_calisma_suresi',
        ]);

        $requiredMinutesColumn = $this->resolveColumn($reportColumns, [
            'gereken_dakika', 'beklenen_dakika', 'normal_dakika', 'required_minutes', 'ivardiyabitsaat', 'ivardiyabassaat',
        ]);

        $lateMinutesColumn = $this->resolveColumn($reportColumns, [
            'igecgelme', 'gecikme_dakika', 'gec_kalma_dakika', 'late_minutes', 'gecgelme',
        ]);

        $earlyExitMinutesColumn = $this->resolveColumn($reportColumns, [
            'ierkencikma', 'erken_cikis_dakika', 'early_exit_minutes', 'erkencikma',
        ]);

        $missingMinutesColumn = $this->resolveColumn($reportColumns, [
            'eksik_dakika', 'mesai_eksik_dakika', 'missing_minutes',
        ]);

        $absenceColumn = $this->resolveColumn($reportColumns, [
            'devamsizlik', 'absence', 'is_absent', 'absent',
        ]);

        $dayStatusColumn = $this->resolveColumn($reportColumns, [
            'gunlukdurum', 'gunluk_durum',
        ]);

        $personnelCodeColumn = $this->resolveColumn($personnelColumns, [
            'personnel_code', 'personel_kodu', 'personelkodu', 'sicil_no', 'sicilno', 'per_kodu', 'kartno',
        ]);

        $personnelIdentityColumn = $this->resolveColumn($personnelColumns, [
            'personnel_identity', 'national_number', 'identity_number', 'tckimlikno',
        ]);

        $personnelMobileColumn = $this->resolveColumn($personnelColumns, [
            'mobil_number', 'mobile_number', 'phone1', 'gsm',
        ]);

        $personnelFullNameColumn = $this->resolveColumn($personnelColumns, [
            'ad_soyad', 'adi_soyadi', 'personel_adi_soyadi', 'full_name',
        ]);

        $personnelFirstNameColumn = $this->resolveColumn($personnelColumns, [
            'personnel_name', 'adi', 'ad', 'first_name', 'personel_adi',
        ]);

        $personnelLastNameColumn = $this->resolveColumn($personnelColumns, [
            'personnel_surname', 'soyadi', 'soyad', 'last_name', 'personel_soyadi',
        ]);

        $baseQuery = DB::table($reportTable.' as report');

        if ($reportEmployeeIdColumn !== null && $personnelEmployeeIdColumn !== null) {
            $baseQuery->leftJoin($personnelTable.' as personnel', function ($join) use (
                $reportEmployeeIdColumn,
                $personnelEmployeeIdColumn,
                $reportCompanyColumn,
                $personnelCompanyColumn
            ): void {
                $join->on('report.'.$reportEmployeeIdColumn, '=', 'personnel.'.$personnelEmployeeIdColumn);

                if ($reportCompanyColumn !== null && $personnelCompanyColumn !== null) {
                    $join->on('report.'.$reportCompanyColumn, '=', 'personnel.'.$personnelCompanyColumn);
                }
            });
        }

        if ($useMappedPersonnel) {
            $baseQuery->whereNotNull('personnel.speedup_personelid')
                ->where('personnel.speedup_personelid', '!=', '');
        }

        $employeeIdSelect = $useMappedPersonnel && $personnelDisplayEmployeeIdColumn !== null
            ? 'personnel.'.$personnelDisplayEmployeeIdColumn.' as employee_id'
            : $this->sqlSelect('report', $reportEmployeeIdColumn, 'employee_id');

        $personnelNameSelect = $personnelFullNameColumn !== null
            ? 'personnel.'.$personnelFullNameColumn
            : ($personnelFirstNameColumn !== null && $personnelLastNameColumn !== null
                ? "TRIM(CONCAT(COALESCE(personnel.$personnelFirstNameColumn, ''), ' ', COALESCE(personnel.$personnelLastNameColumn, '')))"
                : "''");

        $baseQuery->select([
            DB::raw($this->sqlSelect('report', $reportDateColumn, 'attendance_date')),
            DB::raw($employeeIdSelect),
            DB::raw($this->sqlSelect('personnel', $personnelCodeColumn, 'employee_code')),
            DB::raw($personnelNameSelect.' as employee_name'),
            DB::raw($this->sqlSelect('personnel', $personnelIdentityColumn, 'national_number')),
            DB::raw($this->sqlSelect('personnel', $personnelMobileColumn, 'mobile_number')),
            DB::raw($this->sqlSelect('report', $checkInColumn, 'check_in_value')),
            DB::raw($this->sqlSelect('report', $checkOutColumn, 'check_out_value')),
            DB::raw($this->sqlSelect('report', $workedMinutesColumn, 'worked_minutes_value')),
            DB::raw($this->sqlSelect('report', $requiredMinutesColumn, 'required_minutes_value')),
            DB::raw($this->sqlSelect('report', $lateMinutesColumn, 'late_minutes_value')),
            DB::raw($this->sqlSelect('report', $earlyExitMinutesColumn, 'early_exit_minutes_value')),
            DB::raw($this->sqlSelect('report', $missingMinutesColumn, 'missing_minutes_value')),
            DB::raw($this->sqlSelect('report', $absenceColumn, 'absence_value')),
            DB::raw($this->sqlSelect('report', $dayStatusColumn, 'day_status')),
            DB::raw($this->sqlSelect('report', $reportCompanyColumn, 'company')),
        ]);

        if ($reportDateColumn !== null && $dateFrom !== '' && $dateTo !== '') {
            $baseQuery->whereBetween(DB::raw('DATE(report.'.$reportDateColumn.')'), [$dateFrom, $dateTo]);
        }

        if ($searchTerm !== '') {
            $like = '%'.$searchTerm.'%';

            $baseQuery->where(function (Builder $query) use (
                $like,
                $reportEmployeeIdColumn,
                $personnelDisplayEmployeeIdColumn,
                $personnelCodeColumn,
                $personnelFullNameColumn,
                $personnelFirstNameColumn,
                $personnelLastNameColumn
            ): void {
                if ($reportEmployeeIdColumn !== null) {
                    $query->orWhere('report.'.$reportEmployeeIdColumn, 'like', $like);
                }

                if ($personnelDisplayEmployeeIdColumn !== null) {
                    $query->orWhere('personnel.'.$personnelDisplayEmployeeIdColumn, 'like', $like);
                }

                if ($personnelCodeColumn !== null) {
                    $query->orWhere('personnel.'.$personnelCodeColumn, 'like', $like);
                }

                if ($personnelFullNameColumn !== null) {
                    $query->orWhere('personnel.'.$personnelFullNameColumn, 'like', $like);
                }

                if ($personnelFirstNameColumn !== null) {
                    $query->orWhere('personnel.'.$personnelFirstNameColumn, 'like', $like);
                }

                if ($personnelLastNameColumn !== null) {
                    $query->orWhere('personnel.'.$personnelLastNameColumn, 'like', $like);
                }
            });
        }

        if ($reportDateColumn !== null) {
            $baseQuery->orderBy('report.'.$reportDateColumn, 'desc');
        }

        return [
            'daily' => clone $baseQuery,
            'summary' => clone $baseQuery,
        ];
    }

    /**
     * @param  array<int, string>  $availableColumns
     * @param  array<int, string>  $candidates
     */
    private function resolveColumn(array $availableColumns, array $candidates): ?string
    {
        $map = [];

        foreach ($availableColumns as $column) {
            $map[strtolower($column)] = $column;
        }

        foreach ($candidates as $candidate) {
            $normalizedCandidate = strtolower(str_replace([' ', '-', '.'], '_', $candidate));

            if (isset($map[$normalizedCandidate])) {
                return $map[$normalizedCandidate];
            }

            foreach ($map as $normalizedColumn => $actualColumn) {
                if (str_replace('_', '', $normalizedColumn) === str_replace('_', '', $normalizedCandidate)) {
                    return $actualColumn;
                }
            }
        }

        return null;
    }

    private function sqlSelect(string $tableAlias, ?string $column, string $alias): string
    {
        if ($column === null) {
            return 'NULL as '.$alias;
        }

        return $tableAlias.'.'.$column.' as '.$alias;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRow(object $row): array
    {
        $date = $this->formatDate((string) ($row->attendance_date ?? ''));
        $checkIn = $this->formatTime((string) ($row->check_in_value ?? ''));
        $checkOut = $this->formatTime((string) ($row->check_out_value ?? ''));

        $workedMinutes = $this->resolveWorkedMinutes($row->worked_minutes_value ?? null, $checkIn, $checkOut);
        $requiredMinutes = $this->toMinutes($row->required_minutes_value ?? null);
        $lateMinutes = $this->toMinutes($row->late_minutes_value ?? null);
        $earlyExitMinutes = $this->toMinutes($row->early_exit_minutes_value ?? null);
        $missingMinutes = $this->toMinutes($row->missing_minutes_value ?? null);
        $absenceFlag = $this->toBool($row->absence_value ?? null);
        $dayStatus = trim((string) ($row->day_status ?? ''));

        if ($requiredMinutes > 0 && $workedMinutes > 0 && $missingMinutes === 0) {
            $missingMinutes = max($requiredMinutes - $workedMinutes, 0);
        }

        $isWeekendHoliday = $this->isWeekendHolidayStatus($dayStatus);
        $isAbsent = ! $isWeekendHoliday && ($absenceFlag || ($checkIn === '' && $checkOut === ''));

        if ($isWeekendHoliday) {
            $lateMinutes = 0;
            $earlyExitMinutes = 0;
            $missingMinutes = 0;
        }

        $employeeId = trim((string) ($row->employee_id ?? ''));
        $company = trim((string) ($row->company ?? ''));

        return [
            'attendance_date' => $date,
            'month_key' => $this->extractMonthKey($date),
            'employee_id' => $employeeId,
            'employee_code' => trim((string) ($row->employee_code ?? '')),
            'employee_name' => trim((string) ($row->employee_name ?? '')),
            'national_number' => trim((string) ($row->national_number ?? '')),
            'mobile_number' => trim((string) ($row->mobile_number ?? '')),
            'company' => $company,
            'speedup_personnel_key' => $company !== '' ? $company.'|'.$employeeId : $employeeId,
            'day_status' => $dayStatus,
            'is_weekend_holiday' => $isWeekendHoliday,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'late_minutes' => max($lateMinutes, 0),
            'early_exit_minutes' => max($earlyExitMinutes, 0),
            'worked_minutes' => max($workedMinutes, 0),
            'missing_minutes' => max($missingMinutes, 0),
            'is_absent' => $isAbsent,
        ];
    }

    private function formatDate(string $value): string
    {
        $parsed = $this->parseDate($value);

        return $parsed?->format('Y-m-d') ?? trim($value);
    }

    private function formatTime(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $value, $matches) === 1) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        if (preg_match('/^(\d{1,2})(\d{2})$/', $value, $matches) === 1) {
            return str_pad($matches[1], 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return $value;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd.m.Y', 'd/m/Y', 'Y/m/d', 'm/d/Y', 'Y-m-d H:i:s'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolveWorkedMinutes(mixed $rawWorkedMinutes, string $checkIn, string $checkOut): int
    {
        $workedMinutes = $this->toMinutes($rawWorkedMinutes);

        if ($workedMinutes > 0) {
            return $workedMinutes;
        }

        if ($checkIn === '' || $checkOut === '') {
            return 0;
        }

        try {
            $in = Carbon::createFromFormat('H:i', $checkIn);
            $out = Carbon::createFromFormat('H:i', $checkOut);

            if ($out->lessThan($in)) {
                $out->addDay();
            }

            return max($in->diffInMinutes($out), 0);
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function extractMonthKey(string $date): string
    {
        $parsed = $this->parseDate($date);

        return $parsed?->format('Y-m') ?? 'Unknown';
    }

    private function toMinutes(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        $stringValue = trim((string) $value);

        if ($stringValue === '') {
            return 0;
        }

        if (is_numeric($stringValue)) {
            return max((int) round((float) $stringValue), 0);
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $stringValue, $matches) === 1) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    }

    private function toBool(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'evet'], true);
    }

    private function isWeekendHolidayStatus(string $dayStatus): bool
    {
        if ($dayStatus === '') {
            return false;
        }

        $normalized = mb_strtoupper(trim($dayStatus), 'UTF-8');

        return str_contains($normalized, 'HAFTA TATİLİ') || str_contains($normalized, 'HAFTA TATILI');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int|string>
     */
    private function buildSummaryCards(Collection $rows): array
    {
        $totalWorkedMinutes = (int) $rows->sum('worked_minutes');
        $totalMissingMinutes = (int) $rows->sum('missing_minutes');

        return [
            'total_days' => $rows->count(),
            'late_entries' => $rows->where('late_minutes', '>', 0)->count(),
            'early_exits' => $rows->where('early_exit_minutes', '>', 0)->count(),
            'absences' => $rows->where('is_absent', true)->count(),
            'work_time_lack_hours' => $this->minutesToHourText($totalMissingMinutes),
            'worked_hours' => $this->minutesToHourText($totalWorkedMinutes),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, int|string>>
     */
    private function buildMonthlySummary(Collection $rows): Collection
    {
        return $rows
            ->groupBy('month_key')
            ->map(function (Collection $monthRows, string $month): array {
                $workedMinutes = (int) $monthRows->sum('worked_minutes');
                $missingMinutes = (int) $monthRows->sum('missing_minutes');

                return [
                    'month' => $month,
                    'records' => $monthRows->count(),
                    'absences' => $monthRows->where('is_absent', true)->count(),
                    'late_entries' => $monthRows->where('late_minutes', '>', 0)->count(),
                    'early_exits' => $monthRows->where('early_exit_minutes', '>', 0)->count(),
                    'worked_hours' => $this->minutesToHourText($workedMinutes),
                    'work_time_lack_hours' => $this->minutesToHourText($missingMinutes),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    private function minutesToHourText(int $minutes): string
    {
        $safeMinutes = max($minutes, 0);
        $hours = intdiv($safeMinutes, 60);
        $remainingMinutes = $safeMinutes % 60;

        return $hours.':'.str_pad((string) $remainingMinutes, 2, '0', STR_PAD_LEFT);
    }
}
