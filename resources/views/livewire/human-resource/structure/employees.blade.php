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
    <h5 class="card-title m-0 me-2">{{ __('Employees') }}</h5>
    <div class="col-4">
      <input wire:model.live="searchTerm" autofocus type="text" class="form-control" placeholder="{{ __('Search (ID, Name...)') }}">
    </div>
  </div>
  <div class="table-responsive text-nowrap">
    <table class="table">
      <thead>
        <tr>
          <th class="col-1">{{ __('Photo') }}</th>
          <th class="col-2">{{ __('Personel ID') }}</th>
          <th class="col-2">{{ __('Code') }}</th>
          <th class="col-2">{{ __('First Name') }}</th>
          <th class="col-2">{{ __('Last Name') }}</th>
          <th class="col-2">{{ __('Company') }}</th>
          <th class="col-2">{{ __('Status') }}</th>
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
          <td>{{ $employee->personel_id !== '' ? $employee->personel_id : '---' }}</td>
          <td>{{ $employee->code !== '' ? $employee->code : '---' }}</td>
          <td>{{ $employee->first_name !== '' ? $employee->first_name : '---' }}</td>
          <td>{{ $employee->last_name !== '' ? $employee->last_name : '---' }}</td>
          <td>{{ $employee->company !== '' ? $employee->company : '---' }}</td>
          <td>
            {{ $employee->status_label }}
            @if ($employee->status_raw !== '')
              <small class="text-muted d-block">{{ 'Raw: ' . $employee->status_raw }}</small>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7">
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
