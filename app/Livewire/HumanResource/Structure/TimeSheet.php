<?php

namespace App\Livewire\HumanResource\Structure;

use App\Helpers\TimeSheetReport;
use Livewire\Component;
use Livewire\WithPagination;

class TimeSheet extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $searchTerm = '';

    public int $perPage = 31;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPageState();
    }

    public function updatedDateTo(): void
    {
        $this->resetPageState();
    }

    public function updatedSearchTerm(): void
    {
        $this->resetPageState();
    }

    public function updatedPerPage(): void
    {
        $this->resetPageState();
    }

    public function render(TimeSheetReport $timeSheetReport)
    {
        try {
            $report = $timeSheetReport->report([
                'date_from' => $this->dateFrom,
                'date_to' => $this->dateTo,
                'search_term' => $this->searchTerm,
                'per_page' => $this->perPage,
            ]);

            return view('livewire.human-resource.structure.time-sheet', [
                'hasError' => false,
                'errorMessage' => null,
                'dailyRows' => $report['daily_rows'],
                'summaryCards' => $report['summary_cards'],
                'monthlySummary' => $report['monthly_summary'],
            ]);
        } catch (\Throwable $exception) {
            return view('livewire.human-resource.structure.time-sheet', [
                'hasError' => true,
                'errorMessage' => $exception->getMessage(),
                'dailyRows' => $timeSheetReport->emptyPaginator($this->perPage),
                'summaryCards' => [
                    'total_days' => 0,
                    'late_entries' => 0,
                    'early_exits' => 0,
                    'absences' => 0,
                    'work_time_lack_hours' => '0:00',
                    'worked_hours' => '0:00',
                ],
                'monthlySummary' => collect(),
            ]);
        }
    }

    private function resetPageState(): void
    {
        $this->resetPage();
    }
}
