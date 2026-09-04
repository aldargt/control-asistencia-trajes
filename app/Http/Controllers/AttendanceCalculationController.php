<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCalculation;
use App\Models\ControlPeriod;
use App\Services\AttendanceCalculationService;
use App\Support\MonthlyCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCalculationController extends Controller
{
    public function index(Request $request): View
    {
        $periods = ControlPeriod::query()->whereHas('biometricImport.people.attendanceInterpretations')->orderByDesc('year')->orderByDesc('month')->get();
        $selectedPeriod = $periods->firstWhere('id', $request->integer('control_period_id'));
        $calculations = collect();
        $activeCalculations = collect();
        $withoutMarksCalculations = collect();
        $selectedCalculation = null;
        $calendarWeeks = [];

        if ($selectedPeriod) {
            $calculations = AttendanceCalculation::query()->where('control_period_id', $selectedPeriod->id)
                ->with(['collaborator', 'person'])
                ->withCount(['days as activity_days_count' => fn ($query) => $query->where('status', '!=', 'no_marks')])
                ->get()->sortBy(fn ($item) => mb_strtolower($item->collaborator?->full_name ?? $item->person->source_name))->values();
            $activeCalculations = $calculations->where('activity_days_count', '>', 0)->values();
            $withoutMarksCalculations = $calculations->where('activity_days_count', 0)->values();
            $selectedCalculation = $calculations->firstWhere('id', $request->integer('calculation_id'));
            $selectedCalculation?->load(['days.intervals', 'days.interpretation']);
            if ($selectedCalculation) {
                $calendarWeeks = MonthlyCalendar::weeks(
                    $selectedPeriod->year,
                    $selectedPeriod->month,
                    $selectedCalculation->days->keyBy(fn ($day) => $day->work_date->toDateString())->all(),
                );
            }
        }

        return view('attendance-calculations.index', compact(
            'periods', 'selectedPeriod', 'calculations', 'activeCalculations',
            'withoutMarksCalculations', 'selectedCalculation', 'calendarWeeks',
        ));
    }

    public function store(Request $request, ControlPeriod $controlPeriod, AttendanceCalculationService $service): RedirectResponse
    {
        $service->calculate($controlPeriod);

        return redirect()->route('attendance-calculations.index', ['control_period_id' => $controlPeriod->id])->with('success', 'Horas del período recalculadas correctamente.');
    }
}
