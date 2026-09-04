<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\AttendanceInterpretation;
use App\Models\ControlPeriod;
use App\Services\AttendanceCorrectionService;
use App\Support\MonthlyCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceCorrectionController extends Controller
{
    public function index(Request $request): View
    {
        $periods = ControlPeriod::query()
            ->whereHas('biometricImport.people.attendanceInterpretations', fn ($query) => $query->where('logical_marks_count', '>', 0))
            ->with(['biometricImport.people.attendanceInterpretations' => fn ($query) => $query->where('logical_marks_count', '>', 0)->with('corrections')])
            ->orderByDesc('year')->orderByDesc('month')->get();

        $periodSummaries = $periods->mapWithKeys(fn (ControlPeriod $period): array => [
            $period->id => $this->summarize($period->biometricImport->people->flatMap->attendanceInterpretations),
        ]);
        $selectedPeriod = $periods->firstWhere('id', $request->integer('control_period_id'));
        $people = collect();
        $personSummaries = collect();
        $selectedPerson = null;
        $interpretations = collect();
        $calendarWeeks = [];

        if ($selectedPeriod) {
            $people = $selectedPeriod->biometricImport->people
                ->filter(fn ($person) => $person->attendanceInterpretations->where('logical_marks_count', '>', 0)->isNotEmpty())
                ->sortBy(fn ($person) => mb_strtolower($person->collaborator?->full_name ?? $person->source_name))->values();
            $personSummaries = $people->mapWithKeys(fn ($person): array => [
                $person->id => $this->summarize($person->attendanceInterpretations->where('logical_marks_count', '>', 0)),
            ]);
            $selectedPerson = $people->firstWhere('id', $request->integer('person_id'));
        }

        if ($selectedPerson) {
            $interpretations = AttendanceInterpretation::query()
                ->where('biometric_import_person_id', $selectedPerson->id)->where('logical_marks_count', '>', 0)
                ->with(['marks.sources.day', 'person.collaborator', 'person.biometricImport.controlPeriod', 'corrections.marks', 'corrections.administrator'])
                ->orderBy('work_date')->get();
            $interpretations->each(fn (AttendanceInterpretation $interpretation) => $interpretation->setRelation('activeCorrectionRecord', $interpretation->activeCorrection()));
            $calendarWeeks = MonthlyCalendar::weeks(
                $selectedPeriod->year,
                $selectedPeriod->month,
                $interpretations->keyBy(fn ($interpretation) => $interpretation->work_date->toDateString())->all(),
            );
        }

        return view('attendance-corrections.index', compact(
            'interpretations', 'periods', 'periodSummaries', 'selectedPeriod', 'people',
            'personSummaries', 'selectedPerson', 'calendarWeeks',
        ));
    }

    public function store(StoreAttendanceCorrectionRequest $request, AttendanceInterpretation $attendanceInterpretation, AttendanceCorrectionService $service): RedirectResponse
    {
        $service->correct($attendanceInterpretation, $request->user(), $request->validated());
        $attendanceInterpretation->loadMissing('person.collaborator');
        $person = $attendanceInterpretation->person->collaborator?->full_name ?? $attendanceInterpretation->person->source_name;
        $date = $attendanceInterpretation->work_date->locale('es')->translatedFormat('j \d\e F \d\e Y');

        return back()->with('success', "Jornada del {$date} de {$person} corregida correctamente.");
    }

    public function destroy(Request $request, AttendanceInterpretation $attendanceInterpretation, AttendanceCorrectionService $service): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-attendance-corrections'), 403);
        $service->undo($attendanceInterpretation, $request->user());

        return back()->with('success', 'Corrección deshecha. Se restauró la interpretación automática.');
    }

    private function summarize($interpretations): array
    {
        $summary = ['review' => 0, 'corrected' => 0, 'complete' => 0];
        foreach ($interpretations as $interpretation) {
            $activeCorrection = $interpretation->corrections->filter(fn ($correction) => $correction->work_date->toDateString() === $interpretation->work_date->toDateString())
                ->whereNull('superseded_at')->whereNull('undone_at')->isNotEmpty();
            $key = $activeCorrection ? 'corrected' : ($interpretation->status === AttendanceInterpretation::STATUS_REQUIRES_REVIEW ? 'review' : 'complete');
            $summary[$key]++;
        }

        return $summary;
    }
}
