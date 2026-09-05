<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
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
        $periodCalculations = AttendanceCalculation::query()->whereIn('control_period_id', $periods->pluck('id'))->with('days')->get()->groupBy('control_period_id');
        $periodSummaries = $periods->mapWithKeys(function (ControlPeriod $period) use ($periodCalculations): array {
            $calculations = $periodCalculations->get($period->id, collect());
            $days = $calculations->flatMap->days;

            return [$period->id => [
                'calculated' => $calculations->isNotEmpty(),
                'compatible' => $days->where('status', AttendanceCalculationDay::STATUS_RECOGNIZED)->where('source_type', AttendanceCalculationDay::SOURCE_AUTOMATIC)->count(),
                'corrected' => $days->where('status', AttendanceCalculationDay::STATUS_RECOGNIZED)->where('source_type', AttendanceCalculationDay::SOURCE_CORRECTION)->count(),
                'pending' => $days->where('status', AttendanceCalculationDay::STATUS_PENDING)->count(),
                'no_marks' => $days->where('status', AttendanceCalculationDay::STATUS_NO_MARKS)->count(),
            ]];
        });
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
            'withoutMarksCalculations', 'selectedCalculation', 'calendarWeeks', 'periodSummaries',
        ));
    }

    public function store(Request $request, ControlPeriod $controlPeriod, AttendanceCalculationService $service): RedirectResponse
    {
        $service->calculate($controlPeriod);

        return redirect()->route('attendance-calculations.index', ['control_period_id' => $controlPeriod->id])->with('success', 'Horas del período recalculadas correctamente.');
    }
}
