<div>

    @section('title', 'Structure - Time Sheet Linkage')

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
            </li>
            <li class="breadcrumb-item active">{{ __('Human Resource') }}</li>
            <li class="breadcrumb-item active">{{ __('Structure') }}</li>
            <li class="breadcrumb-item active">{{ __('Time Sheet Linkage') }}</li>
        </ol>
    </nav>

    @if($hasError)
        <div class="alert alert-danger" role="alert">
            <h6 class="alert-heading mb-1">{{ __('Linkage Data Is Unavailable') }}</h6>
            <p class="mb-0">{{ __($errorMessage ?? 'Required tables are unavailable.') }}</p>
        </div>
    @endif

    @if($statusMessage)
        <div class="alert alert-info" role="alert">{{ __($statusMessage) }}</div>
    @endif

    <div class="row mb-4">
        <div class="col-md-4 col-12 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">{{ __('Total PrestoXL Employees') }}</small>
                    <h4 class="mb-0">{{ number_format((int) $totalPrestoEmployees) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12 mb-3 mb-md-0">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">{{ __('Mapped Employees') }}</small>
                    <h4 class="mb-0 text-success">{{ number_format((int) $mappedEmployees) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-12">
            <div class="card h-100">
                <div class="card-body">
                    <small class="text-muted">{{ __('Unmapped Employees') }}</small>
                    <h4 class="mb-0 text-danger">{{ number_format((int) $unmappedEmployees) }}</h4>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title m-0">{{ __('PrestoXL Employees') }}</h5>
            <div class="d-flex gap-2">
                <input wire:model.live="searchTerm" type="text" class="form-control" placeholder="{{ __('Search by ID, code, name, or mapped SpeedUP ID') }}">
                <button wire:click="toggleShowUnmappedOnly" type="button" class="btn {{ $showUnmappedOnly ? 'btn-danger' : 'btn-outline-danger' }}">
                    {{ $showUnmappedOnly ? __('Unmapped Only: ON') : __('Unmapped Only: OFF') }}
                </button>
                <select wire:model.live="perPage" class="form-select" style="width: 110px;">
                    <option value="15">15</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('PrestoXL ID') }}</th>
                        <th>{{ __('Employee Code') }}</th>
                        <th>{{ __('Employee Name') }}</th>
                        <th>{{ __('Mapped SpeedUP Personel ID') }}</th>
                        <th>{{ __('Mapped Kurum Kodu') }}</th>
                        <th>{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($employees as $employee)
                        <tr class="{{ $selectedPrestoEmployeeId === (int) $employee->id ? 'table-primary' : '' }}">
                            <td>{{ $employee->per_id ?? '---' }}</td>
                            <td>{{ $employee->personnel_code ?? '---' }}</td>
                            <td>{{ trim(($employee->personnel_name ?? '') . ' ' . ($employee->personnel_surname ?? '')) ?: '---' }}</td>
                            <td>{{ $employee->speedup_personelid ?: '---' }}</td>
                            <td>{{ $employee->speedup_kurumkodu ?: '---' }}</td>
                            <td>
                                <button wire:click="selectEmployee({{ (int) $employee->id }})" class="btn btn-sm btn-outline-primary">
                                    {{ __('Select') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">{{ __('No employees found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">
            {{ $employees->links() }}
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5 col-12 mb-4 mb-lg-0">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title m-0">{{ __('Manual Mapping Editor') }}</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Selected PrestoXL Employee') }}</label>
                        <input type="text" class="form-control" disabled value="{{ $selectedPrestoEmployeeId ? $selectedPrestoEmployeeId : __('Not selected') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('SpeedUP Personel ID') }}</label>
                        <input wire:model="speedupPersonelid" type="text" class="form-control" placeholder="{{ __('Example: 1001') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">{{ __('SpeedUP Kurum Kodu') }}</label>
                        <input wire:model="speedupKurumkodu" type="text" class="form-control" placeholder="{{ __('Example: STERNTEK') }}">
                    </div>

                    <div class="d-flex gap-2">
                        <button wire:click="saveMapping" class="btn btn-primary">{{ __('Save Mapping') }}</button>
                        <button wire:click="clearMapping" class="btn btn-outline-danger">{{ __('Clear Mapping') }}</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7 col-12">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title m-0">{{ __('SpeedUP Candidates') }}</h5>
                    <input wire:model.live="speedupSearchTerm" type="text" class="form-control" style="max-width: 300px;" placeholder="{{ __('Search by personelid, kurumkodu or code') }}">
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Personel ID') }}</th>
                                <th>{{ __('Kurum Kodu') }}</th>
                                <th>{{ __('Employee Code') }}</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @forelse($speedupCandidates as $candidate)
                                <tr>
                                    <td>{{ $candidate->personelid ?? '---' }}</td>
                                    <td>{{ $candidate->kurumkodu ?? '---' }}</td>
                                    <td>{{ $candidate->personnel_code ?? '---' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4">{{ __('No SpeedUP candidates found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</div>
