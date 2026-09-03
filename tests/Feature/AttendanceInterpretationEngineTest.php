<?php

namespace Tests\Feature;

use App\Models\AttendanceInterpretation;
use App\Models\BiometricImport;
use App\Models\BiometricImportDay;
use App\Models\BiometricImportPerson;
use App\Models\BiometricMark;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Models\InterpretedMark;
use App\Models\User;
use App\Services\AttendanceInterpretationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceInterpretationEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private BiometricImport $import;

    private BiometricImportPerson $person;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->create();
        $period = ControlPeriod::create(['year' => 2026, 'month' => 7, 'reference_days' => 26, 'created_by' => $this->administrator->id]);
        $this->import = BiometricImport::create([
            'control_period_id' => $period->id,
            'imported_by' => $this->administrator->id,
            'original_filename' => 'julio-2026.xls',
            'stored_path' => 'biometric-imports/julio-2026.xls',
            'mime_type' => 'application/vnd.ms-excel',
            'file_size' => 100,
            'sha256' => str_repeat('a', 64),
            'imported_at' => now(),
        ]);
        $this->person = $this->import->people()->create([
            'source_biometric_id' => '20',
            'source_name' => 'Jusepe',
            'source_department' => 'Empresa',
            'source_row' => 29,
        ]);
    }

    public function test_two_marks_are_a_complete_entry_exit_sequence(): void
    {
        $this->mark('2026-07-17', '08:00:00');
        $this->mark('2026-07-17', '17:00:00');

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(AttendanceInterpretation::STATUS_COMPLETE, $interpretation->status);
        $this->assertSame([InterpretedMark::TYPE_ENTRY, InterpretedMark::TYPE_EXIT], $interpretation->marks->pluck('type')->all());
    }

    public function test_four_marks_are_a_complete_split_sequence(): void
    {
        foreach (['08:00:00', '12:00:00', '14:00:00', '18:00:00'] as $time) {
            $this->mark('2026-07-17', $time);
        }

        $this->interpret();

        $this->assertSame(
            ['entry', 'exit', 'entry', 'exit'],
            $this->interpretation('2026-07-17')->marks->pluck('type')->all(),
        );
    }

    public function test_early_morning_moves_to_previous_work_date_but_six_oclock_does_not(): void
    {
        $early = $this->mark('2026-07-18', '05:59:59');
        $atCutoff = $this->mark('2026-07-19', '06:00:00');

        $this->interpret();

        $previousDayMark = $this->interpretation('2026-07-17')->marks->first();
        $currentDayMark = $this->interpretation('2026-07-19')->marks->first();
        $this->assertSame($early->id, $previousDayMark->representative_biometric_mark_id);
        $this->assertTrue($previousDayMark->assigned_from_early_morning);
        $this->assertSame($atCutoff->id, $currentDayMark->representative_biometric_mark_id);
        $this->assertFalse($currentDayMark->assigned_from_early_morning);
    }

    public function test_original_marks_are_ordered_chronologically_before_interpretation(): void
    {
        $this->mark('2026-07-17', '17:00:00');
        $this->mark('2026-07-17', '08:00:00');

        $this->interpret();

        $this->assertSame(
            ['08:00:00', '17:00:00'],
            $this->interpretation('2026-07-17')->marks->map(fn (InterpretedMark $mark): string => $mark->occurred_at->format('H:i:s'))->all(),
        );
    }

    public function test_exact_and_near_duplicates_are_consolidated_with_full_traceability(): void
    {
        $sources = [
            $this->mark('2026-07-18', '05:26:00'),
            $this->mark('2026-07-18', '05:26:00'),
            $this->mark('2026-07-18', '05:26:15'),
            $this->mark('2026-07-18', '05:26:59'),
        ];

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $logicalMark = $interpretation->marks->first();
        $this->assertSame(4, $interpretation->original_marks_count);
        $this->assertSame(1, $interpretation->logical_marks_count);
        $this->assertSame(3, $interpretation->duplicate_marks_count);
        $this->assertSame(4, $logicalMark->source_marks_count);
        $this->assertSame(collect($sources)->pluck('id')->all(), $logicalMark->sources->pluck('id')->all());
    }

    public function test_marks_exactly_one_minute_apart_are_consolidated_but_more_than_one_minute_are_not(): void
    {
        $this->mark('2026-07-17', '08:00:00');
        $this->mark('2026-07-17', '08:01:00');
        $this->mark('2026-07-17', '08:02:01');

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(2, $interpretation->logical_marks_count);
        $this->assertSame(1, $interpretation->duplicate_marks_count);
    }

    public function test_three_marks_require_review_without_inventing_types(): void
    {
        foreach (['08:00:00', '12:00:00', '18:00:00'] as $time) {
            $this->mark('2026-07-17', $time);
        }

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(AttendanceInterpretation::STATUS_REQUIRES_REVIEW, $interpretation->status);
        $this->assertSame([null, null, null], $interpretation->marks->pluck('type')->all());
    }

    public function test_unestablished_even_sequences_also_require_review(): void
    {
        foreach (['08:00:00', '10:00:00', '12:00:00', '14:00:00', '16:00:00', '18:00:00'] as $time) {
            $this->mark('2026-07-17', $time);
        }

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(AttendanceInterpretation::STATUS_REQUIRES_REVIEW, $interpretation->status);
        $this->assertSame([null, null, null, null, null, null], $interpretation->marks->pluck('type')->all());
    }

    public function test_day_without_marks_is_not_classified_as_absence(): void
    {
        $this->day('2026-07-17');

        $this->interpret();

        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(AttendanceInterpretation::STATUS_NO_MARKS, $interpretation->status);
        $this->assertSame(0, $interpretation->logical_marks_count);
    }

    public function test_jusepe_case_respects_early_morning_and_cutoff_without_forcing_a_pair(): void
    {
        foreach (['10:00:00', '21:49:00'] as $time) {
            $this->mark('2026-07-17', $time);
        }
        $early = $this->mark('2026-07-18', '04:56:00');
        $afterCutoff = $this->mark('2026-07-18', '06:06:00');

        $this->interpret();

        $july17 = $this->interpretation('2026-07-17');
        $july18 = $this->interpretation('2026-07-18');
        $this->assertSame(3, $july17->logical_marks_count);
        $this->assertSame(AttendanceInterpretation::STATUS_REQUIRES_REVIEW, $july17->status);
        $this->assertTrue($july17->marks->firstWhere('representative_biometric_mark_id', $early->id)->assigned_from_early_morning);
        $this->assertSame([$afterCutoff->id], $july18->marks->pluck('representative_biometric_mark_id')->all());
        $this->assertSame(AttendanceInterpretation::STATUS_REQUIRES_REVIEW, $july18->status);
    }

    public function test_interpretation_does_not_change_original_marks_and_can_be_reprocessed(): void
    {
        $first = $this->mark('2026-07-17', '08:00:00');
        $second = $this->mark('2026-07-17', '17:00:00');
        $this->day('2026-07-17')->update(['original_value' => '08:0017:00']);
        $before = BiometricMark::orderBy('id')->get()->map->only(['id', 'biometric_import_day_id', 'marked_time', 'sequence', 'source_text']);
        $originalDay = $this->day('2026-07-17')->only(['mark_date', 'original_value']);

        $this->interpret();
        $this->interpret();

        $after = BiometricMark::orderBy('id')->get()->map->only(['id', 'biometric_import_day_id', 'marked_time', 'sequence', 'source_text']);
        $this->assertEquals($before, $after);
        $this->assertEquals($originalDay, $this->day('2026-07-17')->only(['mark_date', 'original_value']));
        $this->assertSame(1, AttendanceInterpretation::count());
        $this->assertSame(2, InterpretedMark::count());
        $this->assertDatabaseHas('interpreted_mark_sources', ['biometric_mark_id' => $first->id]);
        $this->assertDatabaseHas('interpreted_mark_sources', ['biometric_mark_id' => $second->id]);
    }

    public function test_only_administrators_can_run_and_view_interpretation(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($viewer)->post(route('biometric-imports.interpretation.store', $this->import))->assertForbidden();
        $this->actingAs($this->administrator)->post(route('biometric-imports.interpretation.store', $this->import))
            ->assertRedirectToRoute('biometric-imports.interpretation.show', $this->import);
        $this->get(route('biometric-imports.interpretation.show', $this->import))
            ->assertOk()
            ->assertSee('Motor de interpretación')
            ->assertSee('No existen personas con actividad biométrica')
            ->assertSee('Ver personas sin marcaciones (1)');
    }

    public function test_interpretation_separates_people_by_activity_and_flags_only_inactive_people_with_marks(): void
    {
        $activeWithMarks = Collaborator::factory()->create(['full_name' => 'Activa con marcas', 'is_active' => true]);
        $inactiveWithMarks = Collaborator::factory()->create(['full_name' => 'Inactiva con marcas', 'is_active' => false]);
        $activeWithoutMarks = Collaborator::factory()->create(['full_name' => 'Activa sin marcas', 'is_active' => true]);
        $inactiveWithoutMarks = Collaborator::factory()->create(['full_name' => 'Inactiva sin marcas', 'is_active' => false]);

        $this->person->update(['collaborator_id' => $activeWithMarks->id]);
        $this->mark('2026-07-17', '08:00:00');
        $inactiveActivityPerson = $this->importPerson('21', 'Inactiva origen', $inactiveWithMarks);
        $this->markFor($inactiveActivityPerson, '2026-07-17', '09:00:00');
        $this->importPerson('22', 'Activa sin marcas origen', $activeWithoutMarks);
        $this->importPerson('23', 'Inactiva sin marcas origen', $inactiveWithoutMarks);
        $this->importPerson('999', 'Sin vínculo');
        $this->import->update(['people_count' => 5, 'matched_people_count' => 4, 'unmatched_people_count' => 1, 'mark_count' => 2]);

        $this->interpret();

        $interpretationResponse = $this->actingAs($this->administrator)->get(route('biometric-imports.interpretation.show', $this->import));
        $html = $interpretationResponse->getContent();
        $interpretationResponse->assertOk()->assertSee('Ver personas sin marcaciones (3)');
        $this->assertSame(2, substr_count($html, 'data-interpretation-person'));
        $this->assertSame(3, substr_count($html, 'data-no-marks-person'));
        $this->assertSame(1, substr_count($html, 'Colaborador inactivo con actividad biométrica durante el período.'));
        $this->assertFalse($inactiveWithMarks->fresh()->is_active);
        $this->assertFalse($inactiveWithoutMarks->fresh()->is_active);
        $this->assertSame(5, $this->import->people()->count());
        $this->assertSame(2, BiometricMark::count());

        $this->get(route('biometric-imports.show', $this->import))
            ->assertOk()
            ->assertSeeInOrder(['Personas detectadas', '5', 'Vinculadas', '4', 'Con marcaciones', '2', 'Sin marcaciones', '3']);
    }

    private function interpret(): void
    {
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
    }

    private function interpretation(string $date): AttendanceInterpretation
    {
        return AttendanceInterpretation::whereDate('work_date', $date)->with('marks.sources')->firstOrFail();
    }

    private function day(string $date): BiometricImportDay
    {
        return $this->person->days()->whereDate('mark_date', $date)->first()
            ?? $this->person->days()->create(['mark_date' => $date]);
    }

    private function mark(string $date, string $time): BiometricMark
    {
        return $this->markFor($this->person, $date, $time);
    }

    private function importPerson(string $biometricId, string $name, ?Collaborator $collaborator = null): BiometricImportPerson
    {
        return $this->import->people()->create([
            'collaborator_id' => $collaborator?->id,
            'source_biometric_id' => $biometricId,
            'source_name' => $name,
            'source_department' => 'Empresa',
            'source_row' => 31 + ($this->import->people()->count() * 2),
        ]);
    }

    private function markFor(BiometricImportPerson $person, string $date, string $time): BiometricMark
    {
        $day = $person->days()->whereDate('mark_date', $date)->first()
            ?? $person->days()->create(['mark_date' => $date]);
        $sequence = $day->marks()->count() + 1;

        return $day->marks()->create([
            'marked_time' => $time,
            'sequence' => $sequence,
            'source_text' => substr($time, 0, 8),
        ]);
    }
}
