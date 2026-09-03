<?php

namespace App\Http\Controllers;

use App\Models\AttendanceInterpretation;
use App\Models\BiometricImport;
use App\Services\AttendanceInterpretationEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AttendanceInterpretationController extends Controller
{
    public function store(BiometricImport $biometricImport, AttendanceInterpretationEngine $engine): RedirectResponse
    {
        $engine->interpret($biometricImport);

        return redirect()->route('biometric-imports.interpretation.show', $biometricImport)
            ->with('success', 'Marcaciones interpretadas correctamente. Las situaciones ambiguas quedaron identificadas para revisión.');
    }

    public function show(BiometricImport $biometricImport): View
    {
        $biometricImport->load([
            'controlPeriod',
            'people.collaborator',
            'people.days.marks',
            'people.attendanceInterpretations.marks.sources.day',
        ]);

        $peopleWithMarks = $biometricImport->people->filter(fn ($person): bool => $person->mark_count > 0)->values();
        $peopleWithoutMarks = $biometricImport->people->filter(fn ($person): bool => $person->mark_count === 0)->values();
        $interpretations = $peopleWithMarks->flatMap->attendanceInterpretations;

        return view('biometric-imports.interpretation', [
            'biometricImport' => $biometricImport,
            'interpretationCount' => $interpretations->count(),
            'reviewCount' => $interpretations->where('status', AttendanceInterpretation::STATUS_REQUIRES_REVIEW)->count(),
            'duplicateCount' => $interpretations->sum('duplicate_marks_count'),
            'peopleWithMarks' => $peopleWithMarks,
            'peopleWithoutMarks' => $peopleWithoutMarks,
        ]);
    }
}
