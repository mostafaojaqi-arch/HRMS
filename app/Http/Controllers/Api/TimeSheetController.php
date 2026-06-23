<?php

namespace App\Http\Controllers\Api;

use App\Helpers\TimeSheetReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeSheetController extends Controller
{
    public function daily(Request $request, TimeSheetReport $timeSheetReport): JsonResponse
    {
        try {
            $report = $timeSheetReport->report([
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'search_term' => $request->query('search'),
                'per_page' => $request->integer('per_page', 31),
            ]);

            return response()->json([
                'ok' => true,
                'summary' => $report['summary_cards'],
                'pagination' => [
                    'current_page' => $report['daily_rows']->currentPage(),
                    'last_page' => $report['daily_rows']->lastPage(),
                    'per_page' => $report['daily_rows']->perPage(),
                    'total' => $report['daily_rows']->total(),
                ],
                'data' => $report['daily_rows']->items(),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function monthly(Request $request, TimeSheetReport $timeSheetReport): JsonResponse
    {
        try {
            $report = $timeSheetReport->report([
                'date_from' => $request->query('date_from'),
                'date_to' => $request->query('date_to'),
                'search_term' => $request->query('search'),
                'per_page' => $request->integer('per_page', 31),
            ]);

            return response()->json([
                'ok' => true,
                'summary' => $report['summary_cards'],
                'data' => $report['monthly_summary']->values(),
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function linkStatus(): JsonResponse
    {
        $personnelTable = 'personnel_employees';
        $employeesTable = 'employees';

        if (! DB::getSchemaBuilder()->hasTable($personnelTable) || ! DB::getSchemaBuilder()->hasTable($employeesTable)) {
            return response()->json([
                'ok' => false,
                'message' => 'Required tables are missing. Ensure personnel_employees and employees exist.',
            ], 422);
        }

        $personnelColumns = DB::getSchemaBuilder()->getColumnListing($personnelTable);
        $perIdColumn = collect(['personelid', 'personel_id', 'personnel_id', 'employee_id', 'sicilno', 'sicil_no', 'per_id'])
            ->first(fn (string $column): bool => in_array($column, $personnelColumns, true));

        if ($perIdColumn === null) {
            return response()->json([
                'ok' => false,
                'message' => 'No employee key column found in personnel_employees.',
            ], 422);
        }

        $totalPersonnel = DB::table($personnelTable)->count();

        $linkedRows = DB::table($personnelTable.' as p')
            ->join($employeesTable.' as e', DB::raw('TRIM(p.'.$perIdColumn.')'), '=', DB::raw('CAST(e.id AS CHAR)'))
            ->count();

        return response()->json([
            'ok' => true,
            'key' => 'personnel_employees.'.$perIdColumn.' -> employees.id',
            'total_personnel_rows' => $totalPersonnel,
            'linked_rows' => $linkedRows,
            'unlinked_rows' => max($totalPersonnel - $linkedRows, 0),
        ]);
    }
}
