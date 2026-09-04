<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionMark;
use App\Models\AttendanceInterpretation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionService
{
    public function correct(AttendanceInterpretation $interpretation, User $administrator, array $data): AttendanceCorrection
    {
        return DB::transaction(function () use ($interpretation, $administrator, $data): AttendanceCorrection {
            $interpretation->newQuery()->whereKey($interpretation)->lockForUpdate()->firstOrFail();
            AttendanceCorrection::query()
                ->where('biometric_import_person_id', $interpretation->biometric_import_person_id)
                ->whereDate('work_date', $interpretation->work_date)
                ->whereNull('superseded_at')
                ->whereNull('undone_at')
                ->update(['superseded_at' => now(), 'superseded_by' => $administrator->id, 'updated_at' => now()]);

            $correction = AttendanceCorrection::create([
                'biometric_import_person_id' => $interpretation->biometric_import_person_id,
                'work_date' => $interpretation->work_date,
                'automatic_status' => $interpretation->status,
                'corrected_by' => $administrator->id,
                'corrected_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            $marks = $interpretation->marks()->with('sources')->whereIn('id', $data['selected_marks'] ?? [])->get()->map(fn ($mark): array => [
                'interpreted_mark_id' => $mark->id,
                'biometric_mark_id' => $mark->representative_biometric_mark_id,
                'occurred_at' => $mark->occurred_at,
                'source_type' => AttendanceCorrectionMark::SOURCE_BIOMETRIC,
                'added_by' => null,
                'source_ids' => $mark->sources->pluck('id')->all(),
            ]);

            foreach (collect($data['manual_marks'] ?? [])->filter() as $value) {
                $marks->push([
                    'interpreted_mark_id' => null,
                    'biometric_mark_id' => null,
                    'occurred_at' => Carbon::parse($value),
                    'source_type' => AttendanceCorrectionMark::SOURCE_MANUAL,
                    'added_by' => $administrator->id,
                    'source_ids' => [],
                ]);
            }

            $marks->sortBy('occurred_at')->values()->each(function (array $mark, int $index) use ($correction): void {
                $sourceIds = $mark['source_ids'];
                unset($mark['source_ids']);
                $correctionMark = $correction->marks()->create([...$mark, 'sequence' => $index + 1]);
                $correctionMark->biometricSources()->attach(collect($sourceIds)->mapWithKeys(
                    fn (int $id, int $sourceIndex): array => [$id => ['sequence' => $sourceIndex + 1]],
                ));
            });

            return $correction->load('marks');
        });
    }

    public function undo(AttendanceInterpretation $interpretation, User $administrator): void
    {
        DB::transaction(function () use ($interpretation, $administrator): void {
            $interpretation->newQuery()->whereKey($interpretation)->lockForUpdate()->firstOrFail();
            AttendanceCorrection::query()
                ->where('biometric_import_person_id', $interpretation->biometric_import_person_id)
                ->whereDate('work_date', $interpretation->work_date)
                ->whereNull('superseded_at')
                ->whereNull('undone_at')
                ->update(['undone_at' => now(), 'undone_by' => $administrator->id, 'updated_at' => now()]);
        });
    }
}
