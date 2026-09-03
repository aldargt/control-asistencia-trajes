<?php

namespace App\Services;

use App\Models\AttendanceInterpretation;
use App\Models\BiometricImport;
use App\Models\BiometricImportPerson;
use App\Models\InterpretedMark;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceInterpretationEngine
{
    private const DUPLICATE_WINDOW_SECONDS = 60;

    private const EARLY_MORNING_CUTOFF = '06:00:00';

    public function interpret(BiometricImport $biometricImport): void
    {
        $biometricImport->load('people.days.marks');

        DB::transaction(function () use ($biometricImport): void {
            foreach ($biometricImport->people as $person) {
                $person->attendanceInterpretations()->delete();
                $this->persistPerson($person);
            }
        });
    }

    public function buildTimeline(array $originalMarks, array $calendarDates): array
    {
        usort($originalMarks, function (array $left, array $right): int {
            $dateComparison = $left['occurred_at']->getTimestamp() <=> $right['occurred_at']->getTimestamp();

            return $dateComparison !== 0 ? $dateComparison : $left['id'] <=> $right['id'];
        });

        $clusters = [];

        foreach ($originalMarks as $mark) {
            $lastClusterIndex = array_key_last($clusters);

            if ($lastClusterIndex === null || $clusters[$lastClusterIndex]['last_at']->diffInSeconds($mark['occurred_at']) > self::DUPLICATE_WINDOW_SECONDS) {
                $clusters[] = [
                    'occurred_at' => $mark['occurred_at'],
                    'last_at' => $mark['occurred_at'],
                    'sources' => [$mark],
                ];

                continue;
            }

            $clusters[$lastClusterIndex]['last_at'] = $mark['occurred_at'];
            $clusters[$lastClusterIndex]['sources'][] = $mark;
        }

        $grouped = [];

        foreach ($clusters as $cluster) {
            $workDate = $cluster['occurred_at']->format('H:i:s') < self::EARLY_MORNING_CUTOFF
                ? $cluster['occurred_at']->copy()->subDay()->toDateString()
                : $cluster['occurred_at']->toDateString();
            $cluster['work_date'] = $workDate;
            $cluster['assigned_from_early_morning'] = $workDate !== $cluster['occurred_at']->toDateString();
            unset($cluster['last_at']);
            $grouped[$workDate][] = $cluster;
        }

        $workDates = array_values(array_unique([...$calendarDates, ...array_keys($grouped)]));
        sort($workDates);
        $timeline = [];

        foreach ($workDates as $workDate) {
            $marks = $grouped[$workDate] ?? [];
            $logicalCount = count($marks);
            $status = match (true) {
                $logicalCount === 0 => AttendanceInterpretation::STATUS_NO_MARKS,
                in_array($logicalCount, [2, 4], true) => AttendanceInterpretation::STATUS_COMPLETE,
                default => AttendanceInterpretation::STATUS_REQUIRES_REVIEW,
            };

            foreach ($marks as $index => &$mark) {
                $mark['sequence'] = $index + 1;
                $mark['type'] = $status === AttendanceInterpretation::STATUS_COMPLETE
                    ? (($index + 1) % 2 === 1 ? InterpretedMark::TYPE_ENTRY : InterpretedMark::TYPE_EXIT)
                    : null;
            }
            unset($mark);

            $originalCount = array_sum(array_map(fn (array $mark): int => count($mark['sources']), $marks));
            $timeline[] = [
                'work_date' => $workDate,
                'status' => $status,
                'original_marks_count' => $originalCount,
                'logical_marks_count' => $logicalCount,
                'duplicate_marks_count' => $originalCount - $logicalCount,
                'marks' => $marks,
            ];
        }

        return $timeline;
    }

    private function persistPerson(BiometricImportPerson $person): void
    {
        $originalMarks = [];
        $calendarDates = [];

        foreach ($person->days as $day) {
            $calendarDates[] = $day->mark_date->toDateString();

            foreach ($day->marks as $mark) {
                $originalMarks[] = [
                    'id' => $mark->id,
                    'occurred_at' => Carbon::parse($day->mark_date->toDateString().' '.$mark->marked_time),
                ];
            }
        }

        foreach ($this->buildTimeline($originalMarks, $calendarDates) as $result) {
            $interpretation = $person->attendanceInterpretations()->create([
                'work_date' => $result['work_date'],
                'status' => $result['status'],
                'original_marks_count' => $result['original_marks_count'],
                'logical_marks_count' => $result['logical_marks_count'],
                'duplicate_marks_count' => $result['duplicate_marks_count'],
                'interpreted_at' => now(),
            ]);

            foreach ($result['marks'] as $logicalMark) {
                $interpretedMark = $interpretation->marks()->create([
                    'representative_biometric_mark_id' => $logicalMark['sources'][0]['id'],
                    'occurred_at' => $logicalMark['occurred_at'],
                    'sequence' => $logicalMark['sequence'],
                    'type' => $logicalMark['type'],
                    'source_marks_count' => count($logicalMark['sources']),
                    'assigned_from_early_morning' => $logicalMark['assigned_from_early_morning'],
                ]);
                $interpretedMark->sources()->attach(collect($logicalMark['sources'])->mapWithKeys(
                    fn (array $source, int $index): array => [$source['id'] => ['sequence' => $index + 1]],
                ));
            }
        }
    }
}
