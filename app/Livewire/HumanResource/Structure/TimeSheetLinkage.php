<?php

namespace App\Livewire\HumanResource\Structure;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class TimeSheetLinkage extends Component
{
    use WithPagination;

    public string $searchTerm = '';

    public string $speedupSearchTerm = '';

    public bool $showUnmappedOnly = false;

    public int $perPage = 20;

    public ?int $selectedPrestoEmployeeId = null;

    public string $speedupPersonelid = '';

    public string $speedupKurumkodu = '';

    public ?string $statusMessage = null;

    public bool $hasError = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (! Schema::hasTable('prestoXL_employees') || ! Schema::hasTable('speedup_personnel')) {
            $this->hasError = true;
            $this->errorMessage = 'Required tables are missing. Please import PrestoXL and SpeedUP data first.';
        }
    }

    public function updatedSearchTerm(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedShowUnmappedOnly(): void
    {
        $this->resetPage();
    }

    public function toggleShowUnmappedOnly(): void
    {
        $this->showUnmappedOnly = ! $this->showUnmappedOnly;
        $this->resetPage();
    }

    public function selectEmployee(int $employeeId): void
    {
        $employee = DB::table('prestoXL_employees')->where('id', $employeeId)->first();

        if ($employee === null) {
            return;
        }

        $this->selectedPrestoEmployeeId = (int) $employee->id;
        $this->speedupPersonelid = trim((string) ($employee->speedup_personelid ?? ''));
        $this->speedupKurumkodu = trim((string) ($employee->speedup_kurumkodu ?? ''));
        $this->statusMessage = null;
    }

    public function saveMapping(): void
    {
        if ($this->selectedPrestoEmployeeId === null) {
            $this->statusMessage = 'Please select a PrestoXL employee first.';

            return;
        }

        $personelid = trim($this->speedupPersonelid);
        $kurumkodu = trim($this->speedupKurumkodu);

        if ($personelid === '' || $kurumkodu === '') {
            $this->statusMessage = 'SpeedUP Personel ID and Kurum Kodu are required.';

            return;
        }

        $existsInSpeedup = DB::table('speedup_personnel')
            ->where('personelid', $personelid)
            ->where('kurumkodu', $kurumkodu)
            ->exists();

        if (! $existsInSpeedup) {
            $this->statusMessage = 'No SpeedUP personnel record found for the provided Personel ID and Kurum Kodu.';

            return;
        }

        DB::table('prestoXL_employees')
            ->where('id', $this->selectedPrestoEmployeeId)
            ->update([
                'speedup_personelid' => $personelid,
                'speedup_kurumkodu' => $kurumkodu,
                'updated_at' => now(),
            ]);

        $this->statusMessage = 'Mapping saved successfully.';
    }

    public function clearMapping(): void
    {
        if ($this->selectedPrestoEmployeeId === null) {
            return;
        }

        DB::table('prestoXL_employees')
            ->where('id', $this->selectedPrestoEmployeeId)
            ->update([
                'speedup_personelid' => null,
                'speedup_kurumkodu' => null,
                'updated_at' => now(),
            ]);

        $this->speedupPersonelid = '';
        $this->speedupKurumkodu = '';
        $this->statusMessage = 'Mapping cleared.';
    }

    public function render()
    {
        if ($this->hasError) {
            return view('livewire.human-resource.structure.time-sheet-linkage', [
                'employees' => collect(),
                'speedupCandidates' => collect(),
                'totalPrestoEmployees' => 0,
                'mappedEmployees' => 0,
                'unmappedEmployees' => 0,
            ]);
        }

        $employees = DB::table('prestoXL_employees')
            ->when(trim($this->searchTerm) !== '', function (Builder $query): void {
                $search = '%'.trim($this->searchTerm).'%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('per_id', 'like', $search)
                        ->orWhere('personnel_code', 'like', $search)
                        ->orWhere('personnel_name', 'like', $search)
                        ->orWhere('personnel_surname', 'like', $search)
                        ->orWhere('speedup_personelid', 'like', $search);
                });
            })
            ->when($this->showUnmappedOnly, function (Builder $query): void {
                $query->where(function (Builder $inner): void {
                    $inner->whereNull('speedup_personelid')
                        ->orWhere('speedup_personelid', '');
                });
            })
            ->orderBy('personnel_name')
            ->orderBy('personnel_surname')
            ->paginate($this->perPage);

        $speedupCandidates = DB::table('speedup_personnel')
            ->when(trim($this->speedupSearchTerm) !== '', function (Builder $query): void {
                $search = '%'.trim($this->speedupSearchTerm).'%';

                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('personelid', 'like', $search)
                        ->orWhere('kurumkodu', 'like', $search)
                        ->orWhere('personnel_code', 'like', $search);
                });
            })
            ->orderBy('kurumkodu')
            ->orderBy('personelid')
            ->limit(50)
            ->get();

        $totalPrestoEmployees = DB::table('prestoXL_employees')->count();
        $mappedEmployees = DB::table('prestoXL_employees')
            ->whereNotNull('speedup_personelid')
            ->where('speedup_personelid', '!=', '')
            ->count();

        return view('livewire.human-resource.structure.time-sheet-linkage', [
            'employees' => $employees,
            'speedupCandidates' => $speedupCandidates,
            'totalPrestoEmployees' => $totalPrestoEmployees,
            'mappedEmployees' => $mappedEmployees,
            'unmappedEmployees' => max($totalPrestoEmployees - $mappedEmployees, 0),
        ]);
    }
}
