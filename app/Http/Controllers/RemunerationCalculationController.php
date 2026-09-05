<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
use App\Models\ControlPeriod;
use App\Services\RemunerationCalculationService;
use App\Support\MonthlyCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemunerationCalculationController extends Controller
{
    public function index(Request $request, RemunerationCalculationService $service): View
    {
        $periods = ControlPeriod::query()->whereHas('attendanceCalculations')->orderByDesc('year')->orderByDesc('month')->get();
        $periodCalculations = AttendanceCalculation::query()->whereIn('control_period_id', $periods->pluck('id'))
            ->with(['collaborator.employmentConditions', 'remuneration', 'days'])->get()->groupBy('control_period_id');
        $periodSummaries = $periods->mapWithKeys(function (ControlPeriod $period) use ($periodCalculations, $service): array {
            $summary = ['calculated' => 0, 'blocked' => 0, 'configuration_pending' => 0, 'stale' => 0, 'not_calculated' => 0, 'without_marks' => 0];
            foreach ($periodCalculations->get($period->id, collect()) as $calculation) {
                if ($calculation->days->every(fn ($day) => $day->status === AttendanceCalculationDay::STATUS_NO_MARKS)) {
                    $summary['without_marks']++;
                } elseif (! $calculation->remuneration) {
                    $summary['not_calculated']++;
                } elseif ($service->isStale($calculation->remuneration, $period)) {
                    $summary['stale']++;
                } else {
                    $summary[$calculation->remuneration->status]++;
                }
            }

            return [$period->id => $summary];
        });
        $selectedPeriod = $periods->firstWhere('id', $request->integer('control_period_id'));
        $calculations = $activeCalculations = $withoutMarksCalculations = collect();
        $selectedCalculation = null;
        $calendarWeeks = [];
        $isStale = false;

        if ($selectedPeriod) {
            $calculations = AttendanceCalculation::query()->where('control_period_id', $selectedPeriod->id)
                ->with(['collaborator.employmentConditions', 'person', 'remuneration'])
                ->withCount(['days as activity_days_count' => fn ($query) => $query->where('status', '!=', AttendanceCalculationDay::STATUS_NO_MARKS)])
                ->get()->sortBy(fn ($item) => mb_strtolower($item->collaborator?->full_name ?? $item->person->source_name))->values();
            $activeCalculations = $calculations->where('activity_days_count', '>', 0)->values();
            $withoutMarksCalculations = $calculations->where('activity_days_count', 0)->values();
            $activeCalculations->each(function ($calculation) use ($selectedPeriod, $service): void {
                $calculation->setAttribute('remuneration_is_stale', $calculation->remuneration
                    ? $service->isStale($calculation->remuneration, $selectedPeriod)
                    : false);
            });
            $selectedCalculation = $calculations->firstWhere('id', $request->integer('calculation_id'));
            $selectedCalculation?->load(['days.intervals', 'days.interpretation']);
            if ($selectedCalculation) {
                $calendarWeeks = MonthlyCalendar::weeks($selectedPeriod->year, $selectedPeriod->month, $selectedCalculation->days->keyBy(fn ($day) => $day->work_date->toDateString())->all());
                $isStale = (bool) $selectedCalculation->getAttribute('remuneration_is_stale');
            }
        }

        return view('remunerations.index', compact('periods', 'periodSummaries', 'selectedPeriod', 'calculations', 'activeCalculations', 'withoutMarksCalculations', 'selectedCalculation', 'calendarWeeks', 'isStale'));
    }

    public function store(ControlPeriod $controlPeriod, RemunerationCalculationService $service): RedirectResponse
    {
        $service->calculate($controlPeriod);

        return redirect()->route('remunerations.index', ['control_period_id' => $controlPeriod->id])->with('success', 'Remuneraciones del período procesadas correctamente.');
    }
}
