<?php

namespace App\Services;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
use App\Models\AttendanceInterpretation;
use App\Models\ControlPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceCalculationService
{
    public function calculate(ControlPeriod $period): Collection
    {
        $period->load(['biometricImport.people.collaborator.employmentConditions', 'biometricImport.people.attendanceInterpretations.marks']);
        if (! $period->biometricImport) {
            return collect();
        }

        return DB::transaction(function () use ($period): Collection {
            $results = collect();
            foreach ($period->biometricImport->people as $person) {
                $reference = $person->collaborator ? $period->hourReferenceFor($person->collaborator) : ['status' => 'without_condition'];
                $expected = $this->expectedMinutes($reference);
                $calculation = AttendanceCalculation::updateOrCreate(
                    ['control_period_id' => $period->id, 'biometric_import_person_id' => $person->id],
                    ['collaborator_id' => $person->collaborator_id, 'status' => AttendanceCalculation::STATUS_CONFIGURATION_PENDING, 'balance_status' => null, 'expected_minutes' => $expected, 'recognized_minutes' => 0, 'difference_minutes' => null, 'pending_days' => 0, 'no_marks_days' => 0, 'calculated_at' => now()],
                );
                $calculation->days()->delete();
                $recognized = $pending = $noMarks = 0;

                foreach ($person->attendanceInterpretations as $interpretation) {
                    $result = $this->calculateDay($interpretation);
                    $day = $calculation->days()->create(['attendance_interpretation_id' => $interpretation->id, 'work_date' => $interpretation->work_date, ...$result['day']]);
                    foreach ($result['intervals'] as $index => $interval) {
                        $day->intervals()->create([...$interval, 'sequence' => $index + 1]);
                    }
                    $recognized += $result['day']['recognized_minutes'] ?? 0;
                    $pending += $result['day']['status'] === AttendanceCalculationDay::STATUS_PENDING ? 1 : 0;
                    $noMarks += $result['day']['status'] === AttendanceCalculationDay::STATUS_NO_MARKS ? 1 : 0;
                }

                $status = $expected === null ? AttendanceCalculation::STATUS_CONFIGURATION_PENDING : ($pending > 0 ? AttendanceCalculation::STATUS_PROVISIONAL : AttendanceCalculation::STATUS_COMPLETE);
                $difference = $expected === null ? null : $recognized - $expected;
                $balance = match (true) {
                    $difference === null => null,
                    $difference < 0 => AttendanceCalculation::BALANCE_DEFICIT,
                    $difference > 0 => AttendanceCalculation::BALANCE_SURPLUS,
                    default => AttendanceCalculation::BALANCE_COMPLIANCE,
                };
                $calculation->update(['status' => $status, 'balance_status' => $balance, 'recognized_minutes' => $recognized, 'difference_minutes' => $difference, 'pending_days' => $pending, 'no_marks_days' => $noMarks, 'calculated_at' => now()]);
                $results->push($calculation->fresh('days.intervals'));
            }

            return $results;
        });
    }

    public function calculateDay(AttendanceInterpretation $interpretation): array
    {
        $correction = $interpretation->activeCorrection();
        $marks = $correction ? $correction->marks : $interpretation->marks;
        if (! $correction && $interpretation->status === AttendanceInterpretation::STATUS_NO_MARKS) {
            return ['day' => ['status' => AttendanceCalculationDay::STATUS_NO_MARKS, 'source_type' => null, 'recognized_minutes' => null, 'attendance_correction_id' => null], 'intervals' => []];
        }
        if ((! $correction && $interpretation->status !== AttendanceInterpretation::STATUS_COMPLETE) || $marks->count() < 2 || $marks->count() % 2 !== 0) {
            return ['day' => ['status' => AttendanceCalculationDay::STATUS_PENDING, 'source_type' => $correction ? AttendanceCalculationDay::SOURCE_CORRECTION : AttendanceCalculationDay::SOURCE_AUTOMATIC, 'recognized_minutes' => null, 'attendance_correction_id' => $correction?->id], 'intervals' => []];
        }

        $intervals = [];
        foreach ($marks->values()->chunk(2) as $pair) {
            $pair = $pair->values();
            $start = $pair[0]->occurred_at;
            $end = $pair[1]->occurred_at;
            $minutes = (int) $start->diffInMinutes($end, false);
            if ($minutes < 0) {
                return ['day' => ['status' => AttendanceCalculationDay::STATUS_PENDING, 'source_type' => $correction ? AttendanceCalculationDay::SOURCE_CORRECTION : AttendanceCalculationDay::SOURCE_AUTOMATIC, 'recognized_minutes' => null, 'attendance_correction_id' => $correction?->id], 'intervals' => []];
            }
            $intervals[] = ['started_at' => $start, 'ended_at' => $end, 'minutes' => $minutes];
        }

        return ['day' => ['status' => AttendanceCalculationDay::STATUS_RECOGNIZED, 'source_type' => $correction ? AttendanceCalculationDay::SOURCE_CORRECTION : AttendanceCalculationDay::SOURCE_AUTOMATIC, 'recognized_minutes' => array_sum(array_column($intervals, 'minutes')), 'attendance_correction_id' => $correction?->id], 'intervals' => $intervals];
    }

    private function expectedMinutes(array $reference): ?int
    {
        if (($reference['status'] ?? null) !== 'calculated') {
            return null;
        }
        $minutes = (float) $reference['expected_hours'] * 60;

        return abs($minutes - round($minutes)) < 0.000001 ? (int) round($minutes) : null;
    }
}
