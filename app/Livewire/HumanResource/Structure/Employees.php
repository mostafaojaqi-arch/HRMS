<?php

namespace App\Livewire\HumanResource\Structure;

use App\Helpers\ExternalEmployeeDirectory;
use App\Models\Contract;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class Employees extends Component
{
    use WithPagination;

    // 👉 Variables
    public $searchTerm = null;

    public string $filterPersonalCode = '';

    public string $filterPersonalId = '';

    public string $filterFirstName = '';

    public string $filterLastName = '';

    public string $filterNationalId = '';

    public string $filterEmploymentDate = '';

    public string $filterExitDate = '';

    public array $companyFilter = [];

    public array $employmentStatusFilter = [];

    public array $jobTitleFilter = [];

    public array $jobFilter = [];

    public array $genderFilter = [];

    public array $maritalStatusFilter = [];

    public ?string $sortField = null;

    public string $sortDirection = 'asc';

    public $contracts;

    public $employee;

    public $employeeInfo = [];

    public $isEdit = false;

    public $confirmedId;

    // 👉 Mount
    public function mount()
    {
        $this->contracts = Contract::all();
    }

    // 👉 Reset page on search
    public function updatedSearchTerm(): void
    {
        $this->resetPage();
    }

    public function updated($propertyName): void
    {
        if (str_starts_with((string) $propertyName, 'filter') || str_ends_with((string) $propertyName, 'Filter')) {
            $this->resetPage();
        }
    }

    public function clearAllFilters(): void
    {
        $this->reset(
            'searchTerm',
            'filterPersonalCode',
            'filterPersonalId',
            'filterFirstName',
            'filterLastName',
            'filterNationalId',
            'filterEmploymentDate',
            'filterExitDate',
            'companyFilter',
            'employmentStatusFilter',
            'jobTitleFilter',
            'jobFilter',
            'genderFilter',
            'maritalStatusFilter'
        );

        $this->resetPage();
    }

    public function setAllFilter(string $filterProperty): void
    {
        if (! property_exists($this, $filterProperty)) {
            return;
        }

        $this->{$filterProperty} = [];
        $this->resetPage();
    }

    public function orderAction(string $field): void
    {
        if (! in_array($field, ['employment_date', 'exit_date'], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }

        $this->resetPage();
    }

    // 👉 Render
    public function render()
    {
        $directory = app(ExternalEmployeeDirectory::class);
        $employeeTable = 'personnel_employees';
        $imageTable = 'personnel_images';

        $employeeColumns = Schema::getColumnListing($employeeTable);
        $imageColumns = Schema::getColumnListing($imageTable);
        $searchColumns = $directory->resolveSearchColumns($employeeColumns);
        $resolveColumn = static fn (array $candidates) => collect($candidates)->first(
            fn ($column) => in_array($column, $employeeColumns, true)
        );

        $employeesQuery = DB::table($employeeTable);

        $personalCodeColumn = $resolveColumn(['personnel_code', 'personnel_kodu', 'personel_kodu', 'code']);
        $personalIdColumn = $resolveColumn(['per_id', 'personnel_id', 'personel_id', 'employee_id', 'id']);
        $firstNameColumn = $resolveColumn(['personnel_name', 'first_name', 'name']);
        $lastNameColumn = $resolveColumn(['personnel_surname', 'personnel_soyadi', 'last_name', 'surname']);
        $nationalIdColumn = $resolveColumn(['personnel_identity', 'national_number', 'identity_number']);
        $companyColumn = $resolveColumn(['company_nme', 'company_name', 'company']);
        $jobTitleColumn = $resolveColumn(['meslek_name']);
        $jobColumn = $resolveColumn(['job', 'duty']);
        $genderColumn = $resolveColumn(['gender']);
        $maritalStatusColumn = $resolveColumn(['marital_carpet', 'martial_carpet']);
        $employmentDateColumn = $resolveColumn(['ise_giris_tar']);
        $exitDateColumn = $resolveColumn(['isten_cikis_tar']);

        if ($this->searchTerm !== null && trim((string) $this->searchTerm) !== '' && $searchColumns !== []) {
            $searchTerm = '%'.trim((string) $this->searchTerm).'%';

            $employeesQuery->where(function ($query) use ($searchColumns, $searchTerm): void {
                foreach ($searchColumns as $index => $column) {
                    if ($index === 0) {
                        $query->where($column, 'like', $searchTerm);

                        continue;
                    }

                    $query->orWhere($column, 'like', $searchTerm);
                }
            });
        }

        if ($personalCodeColumn !== null && $this->filterPersonalCode !== '') {
            $employeesQuery->where($personalCodeColumn, 'like', '%'.trim($this->filterPersonalCode).'%');
        }

        if ($personalIdColumn !== null && $this->filterPersonalId !== '') {
            $employeesQuery->where($personalIdColumn, 'like', '%'.trim($this->filterPersonalId).'%');
        }

        if ($firstNameColumn !== null && $this->filterFirstName !== '') {
            $employeesQuery->where($firstNameColumn, 'like', '%'.trim($this->filterFirstName).'%');
        }

        if ($lastNameColumn !== null && $this->filterLastName !== '') {
            $employeesQuery->where($lastNameColumn, 'like', '%'.trim($this->filterLastName).'%');
        }

        if ($nationalIdColumn !== null && $this->filterNationalId !== '') {
            $employeesQuery->where($nationalIdColumn, 'like', '%'.trim($this->filterNationalId).'%');
        }

        if ($employmentDateColumn !== null && $this->filterEmploymentDate !== '') {
            $employeesQuery->where($employmentDateColumn, 'like', '%'.trim($this->filterEmploymentDate).'%');
        }

        if ($exitDateColumn !== null && $this->filterExitDate !== '') {
            $employeesQuery->where($exitDateColumn, 'like', '%'.trim($this->filterExitDate).'%');
        }

        if ($companyColumn !== null && $this->companyFilter !== []) {
            $employeesQuery->whereIn($companyColumn, $this->companyFilter);
        }

        if ($jobTitleColumn !== null && $this->jobTitleFilter !== []) {
            $employeesQuery->whereIn($jobTitleColumn, $this->jobTitleFilter);
        }

        if ($jobColumn !== null && $this->jobFilter !== []) {
            $employeesQuery->whereIn($jobColumn, $this->jobFilter);
        }

        if ($genderColumn !== null && $this->genderFilter !== []) {
            $employeesQuery->whereIn($genderColumn, $this->genderFilter);
        }

        if ($maritalStatusColumn !== null && $this->maritalStatusFilter !== []) {
            $employeesQuery->whereIn($maritalStatusColumn, $this->maritalStatusFilter);
        }

        $isActiveSelected = in_array('active', $this->employmentStatusFilter, true);
        $isDeactiveSelected = in_array('deactive', $this->employmentStatusFilter, true);

        if (in_array('isten_cikis_tar', $employeeColumns, true) && ($isActiveSelected xor $isDeactiveSelected)) {
            if ($isActiveSelected) {
                $employeesQuery->where(function ($query): void {
                    $query->whereNull('isten_cikis_tar')
                        ->orWhere('isten_cikis_tar', '');
                });
            }

            if ($isDeactiveSelected) {
                $employeesQuery->whereNotNull('isten_cikis_tar')
                    ->where('isten_cikis_tar', '!=', '');
            }
        }

        $sortableColumns = [
            'employment_date' => $employmentDateColumn,
            'exit_date' => $exitDateColumn,
        ];

        $selectedSortColumn = $this->sortField !== null ? ($sortableColumns[$this->sortField] ?? null) : null;

        if ($selectedSortColumn !== null) {
            $this->applyDateOrdering($employeesQuery, $selectedSortColumn, $this->sortDirection);
        } else {
            $sortColumn = $directory->resolveSortColumn($employeeColumns);

            if ($sortColumn !== null) {
                $employeesQuery->orderBy($sortColumn);
            }
        }

        $employees = $employeesQuery->paginate(20);

        $employeeRows = $employees->getCollection();

        $employeePersonalIds = $employeeRows
            ->map(fn ($employee) => trim((string) $directory->resolveFieldValue($employee, $employeeColumns, ['per_id', 'personnel_id', 'personel_id', 'employee_id', 'id'])))
            ->filter()
            ->values()
            ->all();

        $employeeCompanies = $employeeRows
            ->map(fn ($employee) => trim((string) $directory->resolveFieldValue($employee, $employeeColumns, ['company', 'company_name', 'company_nme'])))
            ->filter()
            ->values()
            ->all();

        $imageRowsByCompositeKey = collect();

        if ($employeePersonalIds !== [] && $employeeCompanies !== []) {
            DB::table($imageTable)
                ->whereIn('personnel_id', $employeePersonalIds)
                ->whereIn('company', $employeeCompanies)
                ->orderByDesc('import_id')
                ->get()
                ->each(function ($imageRow) use (&$imageRowsByCompositeKey): void {
                    $imagePersonnelId = trim((string) data_get($imageRow, 'personnel_id'));
                    $imageCompany = trim((string) data_get($imageRow, 'company'));

                    if ($imagePersonnelId === '' || $imageCompany === '') {
                        return;
                    }

                    $compositeKey = $imagePersonnelId.'|'.$imageCompany;

                    if (! $imageRowsByCompositeKey->has($compositeKey)) {
                        $imageRowsByCompositeKey->put($compositeKey, $imageRow);
                    }
                });
        }

        $employees->setCollection($employeeRows->map(function ($employee) use ($directory, $employeeColumns, $imageColumns, $imageRowsByCompositeKey) {
            $personalId = trim((string) $directory->resolveFieldValue($employee, $employeeColumns, ['per_id', 'personnel_id', 'personel_id', 'employee_id', 'id']));
            $companyKey = trim((string) $directory->resolveFieldValue($employee, $employeeColumns, ['company', 'company_name', 'company_nme']));
            $imageKey = $personalId !== '' && $companyKey !== '' ? $personalId.'|'.$companyKey : '';
            $image = $imageKey !== '' ? $imageRowsByCompositeKey->get($imageKey) : null;

            $employmentDate = $this->formatDateForDisplay((string) $directory->resolveFieldValue($employee, $employeeColumns, ['ise_giris_tar']));
            $exitDate = $this->formatDateForDisplay((string) $directory->resolveFieldValue($employee, $employeeColumns, ['isten_cikis_tar']));
            $birthDate = $this->formatDateForDisplay((string) $directory->resolveFieldValue($employee, $employeeColumns, ['date_of_birth']));

            return (object) [
                'personal_code' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_code', 'personnel_kodu', 'personel_kodu', 'code']),
                'personal_id' => $personalId,
                'national_id' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_identity', 'national_number', 'identity_number']),
                'company' => $directory->resolveFieldValue($employee, $employeeColumns, ['company_name', 'company_nme', 'company']),
                'employment_date' => $employmentDate,
                'exit_date' => $exitDate,
                'employment_status' => $exitDate === '' ? 'Active' : 'Deactive',
                'birth_date' => $birthDate,
                'age' => $directory->resolveFieldValue($employee, $employeeColumns, ['age']),
                'job_title' => $directory->resolveFieldValue($employee, $employeeColumns, ['meslek_name']),
                'job' => $directory->resolveFieldValue($employee, $employeeColumns, ['job', 'duty']),
                'department' => $directory->resolveFieldValue($employee, $employeeColumns, ['section']),
                'gender' => $directory->resolveFieldValue($employee, $employeeColumns, ['gender']),
                'marital_status' => $directory->resolveFieldValue($employee, $employeeColumns, ['marital_carpet', 'martial_carpet']),
                'phone1' => $directory->resolveFieldValue($employee, $employeeColumns, ['phone1']),
                'phone2' => $directory->resolveFieldValue($employee, $employeeColumns, ['phone2']),
                'mobile_number' => $directory->resolveFieldValue($employee, $employeeColumns, ['mobil_number', 'mobile_number', 'mobile']),
                'first_name' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_name', 'first_name', 'name']),
                'last_name' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_surname', 'personnel_soyadi', 'last_name', 'surname']),
                'photo_url' => $directory->resolvePhotoPath($employee, $image, $employeeColumns, $imageColumns),
            ];
        }));

        $companyOptions = $companyColumn !== null
            ? DB::table($employeeTable)
                ->select($companyColumn)
                ->whereNotNull($companyColumn)
                ->where($companyColumn, '!=', '')
                ->distinct()
                ->orderBy($companyColumn)
                ->pluck($companyColumn)
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all()
            : [];

        $jobTitleOptions = $jobTitleColumn !== null
            ? DB::table($employeeTable)
                ->select($jobTitleColumn)
                ->whereNotNull($jobTitleColumn)
                ->where($jobTitleColumn, '!=', '')
                ->distinct()
                ->orderBy($jobTitleColumn)
                ->pluck($jobTitleColumn)
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all()
            : [];

        $jobOptions = $jobColumn !== null
            ? DB::table($employeeTable)
                ->select($jobColumn)
                ->whereNotNull($jobColumn)
                ->where($jobColumn, '!=', '')
                ->distinct()
                ->orderBy($jobColumn)
                ->pluck($jobColumn)
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all()
            : [];

        $genderOptions = $genderColumn !== null
            ? DB::table($employeeTable)
                ->select($genderColumn)
                ->whereNotNull($genderColumn)
                ->where($genderColumn, '!=', '')
                ->distinct()
                ->orderBy($genderColumn)
                ->pluck($genderColumn)
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all()
            : [];

        $maritalStatusOptions = $maritalStatusColumn !== null
            ? DB::table($employeeTable)
                ->select($maritalStatusColumn)
                ->whereNotNull($maritalStatusColumn)
                ->where($maritalStatusColumn, '!=', '')
                ->distinct()
                ->orderBy($maritalStatusColumn)
                ->pluck($maritalStatusColumn)
                ->map(fn ($value) => (string) $value)
                ->values()
                ->all()
            : [];

        return view('livewire.human-resource.structure.employees', [
            'employees' => $employees,
            'companyOptions' => $companyOptions,
            'jobTitleOptions' => $jobTitleOptions,
            'jobOptions' => $jobOptions,
            'genderOptions' => $genderOptions,
            'maritalStatusOptions' => $maritalStatusOptions,
        ]);
    }

    private function formatDateForDisplay(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $normalized) === 1) {
            return substr($normalized, 0, 10);
        }

        $withoutTime = preg_replace('/\s+\d{1,2}:\d{2}(:\d{2})?(\.\d+)?$/', '', $normalized);

        return trim((string) ($withoutTime ?? $normalized));
    }

    private function applyDateOrdering($query, string $column, string $direction): void
    {
        $safeColumn = str_replace('`', '``', $column);
        $normalizedDateExpression = "COALESCE(\n"
            ."STR_TO_DATE(NULLIF(TRIM(`{$safeColumn}`), ''), '%Y-%m-%d'),\n"
            ."STR_TO_DATE(NULLIF(TRIM(`{$safeColumn}`), ''), '%Y/%m/%d'),\n"
            ."STR_TO_DATE(NULLIF(TRIM(`{$safeColumn}`), ''), '%d/%m/%Y'),\n"
            ."STR_TO_DATE(NULLIF(TRIM(`{$safeColumn}`), ''), '%d.%m.%Y')\n"
            .')';

        $query->orderByRaw("CASE WHEN NULLIF(TRIM(`{$safeColumn}`), '') IS NULL THEN 1 ELSE 0 END ASC");
        $query->orderByRaw("{$normalizedDateExpression} {$direction}");
        $query->orderBy($column, $direction);
    }

    // 👉 Submit employee
    public function submitEmployee()
    {
        $this->validate([
            'employeeInfo.id' => 'required',
            'employeeInfo.contractId' => 'required',
            'employeeInfo.firstName' => 'required',
            'employeeInfo.fatherName' => 'required',
            'employeeInfo.lastName' => 'required',
            'employeeInfo.motherName' => 'required',
            'employeeInfo.birthAndPlace' => 'required',
            'employeeInfo.nationalNumber' => 'required|min:11|max:11',
            'employeeInfo.mobileNumber' => 'required|min:9|max:9|regex:/^[1-9][0-9]*$/',
            'employeeInfo.degree' => 'required',
            'employeeInfo.gender' => 'required',
            'employeeInfo.address' => 'required',
        ]);

        $this->isEdit ? $this->editEmployee() : $this->addEmployee();
    }

    // 👉 Store employee
    public function showCreateEmployeeModal()
    {
        $this->reset('isEdit', 'employeeInfo');
    }

    public function addEmployee()
    {
        $createdEmployee = Employee::create([
            'id' => $this->employeeInfo['id'],
            'contract_id' => $this->employeeInfo['contractId'],
            'first_name' => $this->employeeInfo['firstName'],
            'father_name' => $this->employeeInfo['fatherName'],
            'last_name' => $this->employeeInfo['lastName'],
            'mother_name' => $this->employeeInfo['motherName'],
            'birth_and_place' => $this->employeeInfo['birthAndPlace'],
            'national_number' => $this->employeeInfo['nationalNumber'],
            'mobile_number' => $this->employeeInfo['mobileNumber'],
            'degree' => $this->employeeInfo['degree'],
            'gender' => $this->employeeInfo['gender'],
            'address' => $this->employeeInfo['address'],
            'notes' => isset($this->employeeInfo['notes']) ? $this->employeeInfo['notes'] : null,
        ]);

        $this->dispatch('closeModal', elementId: '#employeeModal');
        $this->dispatch('toastr', type: 'success' /* , title: 'Done!' */, message: __('Going Well!'));

        session()->flash('openTimelineModal', true);

        return redirect()->route('structure-employees-info', ['id' => $createdEmployee->id]);
    }

    // 👉 Update employee
    public function showEditEmployeeModal(Employee $employee)
    {
        $this->isEdit = true;

        $this->employee = $employee;

        $this->employeeInfo['id'] = $employee->id;
        $this->employeeInfo['contractId'] = $employee->contract_id;
        $this->employeeInfo['firstName'] = $employee->first_name;
        $this->employeeInfo['fatherName'] = $employee->father_name;
        $this->employeeInfo['lastName'] = $employee->last_name;
        $this->employeeInfo['motherName'] = $employee->mother_name;
        $this->employeeInfo['birthAndPlace'] = $employee->birth_and_place;
        $this->employeeInfo['nationalNumber'] = $employee->national_number;
        $this->employeeInfo['mobileNumber'] = $employee->mobile_number;
        $this->employeeInfo['degree'] = $employee->degree;
        $this->employeeInfo['gender'] = $employee->gender;
        $this->employeeInfo['address'] = $employee->address;
        $this->employeeInfo['notes'] = $employee->notes;
    }

    public function editEmployee()
    {
        $this->employee->update([
            'id' => $this->employeeInfo['id'],
            'contract_id' => $this->employeeInfo['contractId'],
            'first_name' => $this->employeeInfo['firstName'],
            'father_name' => $this->employeeInfo['fatherName'],
            'last_name' => $this->employeeInfo['lastName'],
            'mother_name' => $this->employeeInfo['motherName'],
            'birth_and_place' => $this->employeeInfo['birthAndPlace'],
            'national_number' => $this->employeeInfo['nationalNumber'],
            'mobile_number' => $this->employeeInfo['mobileNumber'],
            'degree' => $this->employeeInfo['degree'],
            'gender' => $this->employeeInfo['gender'],
            'address' => $this->employeeInfo['address'],
            'notes' => isset($this->employeeInfo['notes']) ? $this->employeeInfo['notes'] : null,
        ]);

        $this->dispatch('closeModal', elementId: '#employeeModal');
        $this->dispatch('toastr', type: 'success' /* , title: 'Done!' */, message: __('Going Well!'));
    }

    // 👉 Delete employee
    public function confirmDeleteEmployee($id)
    {
        $this->confirmedId = $id;
    }

    public function deleteEmployee(Employee $employee)
    {
        $employee->delete();
        $this->dispatch('toastr', type: 'success' /* , title: 'Done!' */, message: __('Going Well!'));
    }
}
