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

    // 👉 Render
    public function render()
    {
        $directory = app(ExternalEmployeeDirectory::class);
        $employeeTable = 'personnel_employees';
        $imageTable = 'personnel_images';

        $employeeColumns = Schema::getColumnListing($employeeTable);
        $imageColumns = Schema::getColumnListing($imageTable);
        $searchColumns = $directory->resolveSearchColumns($employeeColumns);

        $employeesQuery = DB::table($employeeTable);

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

        $sortColumn = $directory->resolveSortColumn($employeeColumns);

        if ($sortColumn !== null) {
            $employeesQuery->orderBy($sortColumn);
        }

        $employees = $employeesQuery->paginate(20);

        $joinColumns = $directory->resolveJoinColumnPair($employeeColumns, $imageColumns);
        $imagesByKey = collect();

        $employeeJoinValues = $employees->getCollection()
            ->map(fn ($employee) => data_get($employee, 'personnel_id') ?? data_get($employee, 'personel_id') ?? data_get($employee, 'id'))
            ->filter()
            ->map(fn ($value) => trim((string) $value))
            ->values()
            ->all();

        if ($joinColumns !== null) {
            $directJoinValues = $employees->getCollection()
                ->map(fn ($employee) => data_get($employee, $joinColumns['employee']))
                ->filter()
                ->values()
                ->all();

            if ($directJoinValues !== []) {
                $imagesByKey = DB::table($imageTable)
                    ->whereIn($joinColumns['image'], $directJoinValues)
                    ->orderByDesc('import_id')
                    ->get()
                    ->groupBy($joinColumns['image']);
            }
        }

        if ($imagesByKey->isEmpty() && in_array('row_payload', $imageColumns, true) && $employeeJoinValues !== []) {
            $payloadImagesByKey = collect();

            DB::table($imageTable)
                ->whereNotNull('row_payload')
                ->orderByDesc('import_id')
                ->get()
                ->each(function ($imageRow) use ($directory, &$payloadImagesByKey): void {
                    $payloadPersonnelId = $directory->resolvePayloadFieldValue(
                        data_get($imageRow, 'row_payload'),
                        ['personnel_id', 'personel_id', 'employee_id', 'id']
                    );

                    if ($payloadPersonnelId === '') {
                        return;
                    }

                    if (! $payloadImagesByKey->has($payloadPersonnelId)) {
                        $payloadImagesByKey->put($payloadPersonnelId, collect([$imageRow]));
                    }
                });

            $imagesByKey = collect($employeeJoinValues)
                ->filter(fn ($personnelId) => $payloadImagesByKey->has($personnelId))
                ->mapWithKeys(fn ($personnelId) => [$personnelId => $payloadImagesByKey->get($personnelId)]);
        }

        $employees->setCollection($employees->getCollection()->map(function ($employee) use ($directory, $employeeColumns, $imageColumns, $imagesByKey, $joinColumns) {
            $image = null;

            if ($joinColumns !== null) {
                $joinValue = data_get($employee, $joinColumns['employee']);
                $image = $imagesByKey->get($joinValue)?->first();
            }

            if ($image === null) {
                $payloadJoinValue = trim((string) ($directory->resolveFieldValue($employee, $employeeColumns, ['personnel_id', 'personel_id', 'employee_id', 'id'])));
                $image = $imagesByKey->get($payloadJoinValue)?->first();
            }

            $statusRawValue = $directory->resolveFieldValue($employee, $employeeColumns, ['ssk_statusu', 'status']);

            return (object) [
                'personel_id' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_id', 'personel_id', 'employee_id', 'id']),
                'code' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_kodu', 'personel_kodu', 'code']),
                'first_name' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_name', 'personel_name', 'first_name', 'name']),
                'last_name' => $directory->resolveFieldValue($employee, $employeeColumns, ['personnel_soyadi', 'personel_soyadi', 'last_name', 'surname']),
                'company' => $directory->resolveFieldValue($employee, $employeeColumns, ['company']),
                'status_raw' => $statusRawValue,
                'status_label' => $directory->resolveStatusLabel($statusRawValue),
                'photo_url' => $directory->resolvePhotoPath($employee, $image, $employeeColumns, $imageColumns),
            ];
        }));

        return view('livewire.human-resource.structure.employees', [
            'employees' => $employees,
        ]);
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
