<div>

@php
  $configData = Helper::appClasses();
@endphp

@section('title', 'Employees - Structure')

@section('page-style')
  <style>
    .btn-tr {
      opacity: 0;
    }

    tr:hover .btn-tr {
      display: inline-block;
      opacity: 1;
    }

    tr:hover .td {
      color: #7367f0 !important;
    }

    .employee-avatar {
      width: 3cm;
      height: 3cm;
      object-fit: cover;
      border-radius: 50%;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .employee-avatar:hover {
      transform: scale(1.08);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
    }

    #photoModalImg {
      width: 100%;
      max-height: 70vh;
      object-fit: contain;
    }

    .employees-table-wrapper {
      width: 100%;
      overflow-x: auto;
    }

    .employees-table {
      min-width: 2600px;
    }

    .employees-table > thead > tr > th,
    .employees-table > tbody > tr > td {
      padding: 0.45rem 0.55rem;
      vertical-align: middle;
      white-space: nowrap;
    }

    .employment-status-pill {
      display: inline-block;
      padding: 0.25rem 0.55rem;
      border-radius: 0.375rem;
      font-size: 0.8125rem;
      font-weight: 600;
      line-height: 1.2;
    }

    .employment-status-pill.active {
      color: #0f5132;
      background-color: #d1e7dd;
      border: 1px solid #badbcc;
    }

    .employment-status-pill.deactive {
      color: #842029;
      background-color: #f8d7da;
      border: 1px solid #f5c2c7;
    }

    .sort-header-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      border: 0;
      background: transparent;
      color: inherit;
      font-weight: 600;
      padding: 0;
    }

    .sort-header-btn .sort-indicator {
      font-size: 0.7rem;
      color: #8592a3;
    }

    .sort-header-btn.active .sort-indicator {
      color: #696cff;
    }
  </style>
@endsection

{{-- Photo modal --}}
<div x-data="{ photoUrl: '', photoName: '' }">

<div class="modal fade" id="photoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-header pb-0 border-0">
        <h5 class="modal-title" x-text="photoName"></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-3">
        <img id="photoModalImg" :src="photoUrl" alt="Employee Photo">
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <div class="d-flex align-items-center gap-2">
      <h5 class="card-title m-0 me-2">{{ __('Employees') }}</h5>
      <span class="badge bg-label-primary">{{ __('Total') . ': ' . number_format($employees->total()) }}</span>
    </div>
    <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
      <div style="min-width: 280px;">
        <input wire:model.live="searchTerm" autofocus type="text" class="form-control" placeholder="{{ __('Search (ID, Name...)') }}">
      </div>
      <button type="button" class="btn btn-outline-secondary" wire:click="clearAllFilters">
        {{ __('Clear Filters') }}
      </button>
    </div>
  </div>
  <div class="table-responsive text-nowrap employees-table-wrapper">
    <table class="table employees-table">
      <thead>
        <tr>
          <th class="col-1"></th>
          <th class="col-2">{{ __('Personal Code') }}</th>
          <th class="col-2">{{ __('Personal ID') }}</th>
          <th class="col-2">{{ __('First Name') }}</th>
          <th class="col-2">{{ __('Last Name') }}</th>
          <th class="col-2">{{ __('National ID') }}</th>
          <th class="col-2">{{ __('Company') }}</th>
          <th class="col-2">
            <button type="button" class="sort-header-btn {{ $sortField === 'employment_date' ? 'active' : '' }}" wire:click="orderAction('employment_date')">
              {{ __('Employment Date') }}
              <span class="sort-indicator">{{ $sortField === 'employment_date' ? ($sortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
            </button>
          </th>
          <th class="col-2">
            <button type="button" class="sort-header-btn {{ $sortField === 'exit_date' ? 'active' : '' }}" wire:click="orderAction('exit_date')">
              {{ __('Exit Date') }}
              <span class="sort-indicator">{{ $sortField === 'exit_date' ? ($sortDirection === 'asc' ? '▲' : '▼') : '↕' }}</span>
            </button>
          </th>
          <th class="col-2">{{ __('Employment Status') }}</th>
          <th class="col-2">{{ __('Birth Date') }}</th>
          <th class="col-1">{{ __('Age') }}</th>
          <th class="col-2">{{ __('Job Title') }}</th>
          <th class="col-2">{{ __('Job') }}</th>
          <th class="col-2">{{ __('Department') }}</th>
          <th class="col-1">{{ __('Gender') }}</th>
          <th class="col-2">{{ __('Marital Status') }}</th>
          <th class="col-2">{{ __('Phone1') }}</th>
          <th class="col-2">{{ __('Phone2') }}</th>
          <th class="col-2">{{ __('Mobile Number') }}</th>
        </tr>
        <tr>
          <th></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterPersonalCode" placeholder="{{ __('Search') }}"></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterPersonalId" placeholder="{{ __('Search') }}"></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterFirstName" placeholder="{{ __('Search') }}"></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterLastName" placeholder="{{ __('Search') }}"></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterNationalId" placeholder="{{ __('Search') }}"></th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($companyFilter) > 0 ? ' (' . count($companyFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="companyAll" wire:click="setAllFilter('companyFilter')" @checked($companyFilter === [])>
                    <label class="form-check-label" for="companyAll">{{ __('All') }}</label>
                  </div>
                </li>
                @foreach ($companyOptions as $index => $option)
                  <li>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="companyOption{{ $index }}" wire:model.live="companyFilter" value="{{ $option }}">
                      <label class="form-check-label" for="companyOption{{ $index }}">{{ $option }}</label>
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterEmploymentDate" placeholder="{{ __('Search') }}"></th>
          <th><input type="text" class="form-control form-control-sm" wire:model.live="filterExitDate" placeholder="{{ __('Search') }}"></th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($employmentStatusFilter) > 0 ? ' (' . count($employmentStatusFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="employmentStatusAll" wire:click="setAllFilter('employmentStatusFilter')" @checked($employmentStatusFilter === [])>
                    <label class="form-check-label" for="employmentStatusAll">{{ __('All') }}</label>
                  </div>
                </li>
                <li>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="employmentStatusActive" wire:model.live="employmentStatusFilter" value="active">
                    <label class="form-check-label" for="employmentStatusActive">{{ __('Active') }}</label>
                  </div>
                </li>
                <li>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="employmentStatusDeactive" wire:model.live="employmentStatusFilter" value="deactive">
                    <label class="form-check-label" for="employmentStatusDeactive">{{ __('Deactive') }}</label>
                  </div>
                </li>
              </ul>
            </div>
          </th>
          <th></th>
          <th></th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($jobTitleFilter) > 0 ? ' (' . count($jobTitleFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="jobTitleAll" wire:click="setAllFilter('jobTitleFilter')" @checked($jobTitleFilter === [])>
                    <label class="form-check-label" for="jobTitleAll">{{ __('All') }}</label>
                  </div>
                </li>
                @foreach ($jobTitleOptions as $index => $option)
                  <li>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="jobTitleOption{{ $index }}" wire:model.live="jobTitleFilter" value="{{ $option }}">
                      <label class="form-check-label" for="jobTitleOption{{ $index }}">{{ $option }}</label>
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($jobFilter) > 0 ? ' (' . count($jobFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="jobAll" wire:click="setAllFilter('jobFilter')" @checked($jobFilter === [])>
                    <label class="form-check-label" for="jobAll">{{ __('All') }}</label>
                  </div>
                </li>
                @foreach ($jobOptions as $index => $option)
                  <li>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="jobOption{{ $index }}" wire:model.live="jobFilter" value="{{ $option }}">
                      <label class="form-check-label" for="jobOption{{ $index }}">{{ $option }}</label>
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </th>
          <th></th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($genderFilter) > 0 ? ' (' . count($genderFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="genderAll" wire:click="setAllFilter('genderFilter')" @checked($genderFilter === [])>
                    <label class="form-check-label" for="genderAll">{{ __('All') }}</label>
                  </div>
                </li>
                @foreach ($genderOptions as $index => $option)
                  <li>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="genderOption{{ $index }}" wire:model.live="genderFilter" value="{{ $option }}">
                      <label class="form-check-label" for="genderOption{{ $index }}">{{ $option }}</label>
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </th>
          <th>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                {{ __('Filter') }}{{ count($maritalStatusFilter) > 0 ? ' (' . count($maritalStatusFilter) . ')' : '' }}
              </button>
              <ul class="dropdown-menu p-2" style="max-height: 260px; overflow-y: auto; min-width: 220px;">
                <li class="mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="maritalAll" wire:click="setAllFilter('maritalStatusFilter')" @checked($maritalStatusFilter === [])>
                    <label class="form-check-label" for="maritalAll">{{ __('All') }}</label>
                  </div>
                </li>
                @foreach ($maritalStatusOptions as $index => $option)
                  <li>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="maritalOption{{ $index }}" wire:model.live="maritalStatusFilter" value="{{ $option }}">
                      <label class="form-check-label" for="maritalOption{{ $index }}">{{ $option }}</label>
                    </div>
                  </li>
                @endforeach
              </ul>
            </div>
          </th>
          <th></th>
          <th></th>
          <th></th>
        </tr>
      </thead>
      <tbody class="table-border-bottom-0">
        @forelse($employees as $employee)
        <tr>
          <td>
            <img
              src="{{ $employee->photo_url }}"
              alt="{{ $employee->first_name }} {{ $employee->last_name }}"
              class="employee-avatar"
              @click="photoUrl = $el.src; photoName = $el.alt; bootstrap.Modal.getOrCreateInstance($el.closest('[x-data]').querySelector('#photoModal')).show()"
            >
          </td>
          <td>{{ $employee->personal_code !== '' ? $employee->personal_code : '---' }}</td>
          <td>{{ $employee->personal_id !== '' ? $employee->personal_id : '---' }}</td>
          <td>{{ $employee->first_name !== '' ? $employee->first_name : '---' }}</td>
          <td>{{ $employee->last_name !== '' ? $employee->last_name : '---' }}</td>
          <td>{{ $employee->national_id !== '' ? $employee->national_id : '---' }}</td>
          <td>{{ $employee->company !== '' ? $employee->company : '---' }}</td>
          <td>{{ $employee->employment_date !== '' ? $employee->employment_date : '---' }}</td>
          <td>{{ $employee->exit_date !== '' ? $employee->exit_date : '---' }}</td>
          <td>
            @if ($employee->employment_status === 'Active')
              <span class="employment-status-pill active">{{ $employee->employment_status }}</span>
            @else
              <span class="employment-status-pill deactive">{{ $employee->employment_status }}</span>
            @endif
          </td>
          <td>{{ $employee->birth_date !== '' ? $employee->birth_date : '---' }}</td>
          <td>{{ $employee->age !== '' ? $employee->age : '---' }}</td>
          <td>{{ $employee->job_title !== '' ? $employee->job_title : '---' }}</td>
          <td>{{ $employee->job !== '' ? $employee->job : '---' }}</td>
          <td>{{ $employee->department !== '' ? $employee->department : '---' }}</td>
          <td>{{ $employee->gender !== '' ? $employee->gender : '---' }}</td>
          <td>{{ $employee->marital_status !== '' ? $employee->marital_status : '---' }}</td>
          <td>{{ $employee->phone1 !== '' ? $employee->phone1 : '---' }}</td>
          <td>{{ $employee->phone2 !== '' ? $employee->phone2 : '---' }}</td>
          <td>{{ $employee->mobile_number !== '' ? $employee->mobile_number : '---' }}</td>
        </tr>
        @empty
        <tr>
          <td colspan="20">
            <div class="mt-2 mb-2" style="text-align: center">
                <h3 class="mb-1 mx-2">{{ __('Oopsie-doodle!') }}</h3>
                <p class="mb-4 mx-2">
                  {{ __('No data found, please sprinkle some data in my virtual bowl, and let the fun begin!') }}
                </p>
                <div>
                  <img src="{{ asset('assets/img/illustrations/page-misc-under-maintenance.png') }}" width="200" class="img-fluid">
                </div>
            </div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="row mt-4">
    {{ $employees->links() }}
  </div>

</div>

</div>

</div>
