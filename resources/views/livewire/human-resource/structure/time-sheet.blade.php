<div>

  @php
    $configData = Helper::appClasses();
  @endphp

  @section('title', 'Structure - Time Sheet')

  <nav aria-label="breadcrumb">
    <ol class="breadcrumb">
      <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
      </li>
      <li class="breadcrumb-item active">{{ __('Human Resource') }}</li>
      <li class="breadcrumb-item active">{{ __('Structure') }}</li>
      <li class="breadcrumb-item active">{{ __('Time Sheet') }}</li>
    </ol>
  </nav>

  @if($hasError)
    <div class="alert alert-danger" role="alert">
      <h6 class="alert-heading mb-1">{{ __('Time Sheet Data Is Unavailable') }}</h6>
      <p class="mb-0">{{ __('Could not load SQL Server report data. Please verify SpeedUP SQL Server settings in the environment variables.') }}</p>
      <hr>
      <small class="text-muted">{{ $errorMessage }}</small>
    </div>
  @endif

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title m-0">{{ __('Filters') }}</h5>
      <a href="{{ route('structure-timesheet-linkage') }}" class="btn btn-sm btn-outline-primary">
        {{ __('Manage Employee Linkage') }}
      </a>
    </div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label">{{ __('From Date') }}</label>
          <input wire:model.live="dateFrom" type="date" class="form-control">
        </div>
        <div class="col-md-3">
          <label class="form-label">{{ __('To Date') }}</label>
          <input wire:model.live="dateTo" type="date" class="form-control">
        </div>
        <div class="col-md-4">
          <label class="form-label">{{ __('Employee Search') }}</label>
          <input wire:model.live="searchTerm" type="text" class="form-control" placeholder="{{ __('Search by employee id, code or name') }}">
        </div>
        <div class="col-md-2">
          <label class="form-label">{{ __('Rows Per Page') }}</label>
          <select wire:model.live="perPage" class="form-select">
            <option value="15">15</option>
            <option value="31">31</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="row mb-4">
    <div class="col-md-2 col-6 mb-3 mb-md-0">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Total Days') }}</small>
          <h4 class="mb-0">{{ number_format((int) $summaryCards['total_days']) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-3 mb-md-0">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Late Entries') }}</small>
          <h4 class="mb-0">{{ number_format((int) $summaryCards['late_entries']) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-3 mb-md-0">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Early Exits') }}</small>
          <h4 class="mb-0">{{ number_format((int) $summaryCards['early_exits']) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-3 mb-md-0">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Absences') }}</small>
          <h4 class="mb-0">{{ number_format((int) $summaryCards['absences']) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-6 mb-3 mb-md-0">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Worked Hours') }}</small>
          <h4 class="mb-0">{{ $summaryCards['worked_hours'] }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-2 col-6">
      <div class="card h-100">
        <div class="card-body">
          <small class="text-muted">{{ __('Work Time Lack') }}</small>
          <h4 class="mb-0">{{ $summaryCards['work_time_lack_hours'] }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="card-title m-0">{{ __('Daily Time Attendance Report') }}</h5>
      <span class="badge bg-label-primary">{{ __('Rows') . ': ' . number_format($dailyRows->total()) }}</span>
    </div>
    <div class="table-responsive text-nowrap">
      <table class="table">
        <thead>
          <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Day Status') }}</th>
            <th>{{ __('Employee ID') }}</th>
            <th>{{ __('Employee Code') }}</th>
            <th>{{ __('Employee Name') }}</th>
            <th>{{ __('Entrance Time') }}</th>
            <th>{{ __('Exit Time') }}</th>
            <th>{{ __('Late Entrance (min)') }}</th>
            <th>{{ __('Early Exit (min)') }}</th>
            <th>{{ __('Work Time Lack (min)') }}</th>
            <th>{{ __('Worked Time (min)') }}</th>
            <th>{{ __('Absent') }}</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($dailyRows as $row)
            <tr>
              <td>{{ $row['attendance_date'] !== '' ? $row['attendance_date'] : '---' }}</td>
              <td>{{ $row['day_status'] !== '' ? $row['day_status'] : '---' }}</td>
              <td>{{ $row['employee_id'] !== '' ? $row['employee_id'] : '---' }}</td>
              <td>{{ $row['employee_code'] !== '' ? $row['employee_code'] : '---' }}</td>
              <td>{{ $row['employee_name'] !== '' ? $row['employee_name'] : '---' }}</td>
              <td>{{ $row['check_in'] !== '' ? $row['check_in'] : '---' }}</td>
              <td>{{ $row['check_out'] !== '' ? $row['check_out'] : '---' }}</td>
              <td>{{ number_format((int) $row['late_minutes']) }}</td>
              <td>{{ number_format((int) $row['early_exit_minutes']) }}</td>
              <td>{{ number_format((int) $row['missing_minutes']) }}</td>
              <td>{{ number_format((int) $row['worked_minutes']) }}</td>
              <td>
                @if ($row['is_absent'])
                  <span class="badge bg-label-danger">{{ __('Yes') }}</span>
                @else
                  <span class="badge bg-label-success">{{ __('No') }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="12" class="text-center py-4">{{ __('No attendance records found for the selected filters.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div class="card-body">
      {{ $dailyRows->links() }}
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h5 class="card-title m-0">{{ __('Monthly Work Time Report') }}</h5>
    </div>
    <div class="table-responsive text-nowrap">
      <table class="table">
        <thead>
          <tr>
            <th>{{ __('Month') }}</th>
            <th>{{ __('Records') }}</th>
            <th>{{ __('Absences') }}</th>
            <th>{{ __('Late Entries') }}</th>
            <th>{{ __('Early Exits') }}</th>
            <th>{{ __('Worked Hours') }}</th>
            <th>{{ __('Work Time Lack') }}</th>
          </tr>
        </thead>
        <tbody class="table-border-bottom-0">
          @forelse($monthlySummary as $month)
            <tr>
              <td>{{ $month['month'] }}</td>
              <td>{{ number_format((int) $month['records']) }}</td>
              <td>{{ number_format((int) $month['absences']) }}</td>
              <td>{{ number_format((int) $month['late_entries']) }}</td>
              <td>{{ number_format((int) $month['early_exits']) }}</td>
              <td>{{ $month['worked_hours'] }}</td>
              <td>{{ $month['work_time_lack_hours'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center py-4">{{ __('No monthly summary data available.') }}</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
