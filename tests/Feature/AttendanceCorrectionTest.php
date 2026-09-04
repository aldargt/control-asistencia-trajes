<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceCorrectionMark;
use App\Models\AttendanceInterpretation;
use App\Models\BiometricImport;
use App\Models\BiometricImportPerson;
use App\Models\ControlPeriod;
use App\Models\User;
use App\Services\AttendanceInterpretationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
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
        $this->import = BiometricImport::create(['control_period_id' => $period->id, 'imported_by' => $this->administrator->id, 'original_filename' => 'julio.xls', 'stored_path' => 'biometric-imports/julio.xls', 'file_size' => 100, 'sha256' => str_repeat('a', 64), 'imported_at' => now()]);
        $this->person = $this->import->people()->create(['source_biometric_id' => '20', 'source_name' => 'Jusepe', 'source_row' => 5]);
    }

    public function test_three_marks_can_be_corrected_by_excluding_one_without_altering_origin(): void
    {
        $this->addMarks('2026-07-17', ['09:07:00', '19:38:00', '22:44:00']);
        $interpretation = $this->interpretation('2026-07-17');
        $original = $this->originalSnapshot();

        $this->postCorrection($interpretation, [$interpretation->marks[0]->id, $interpretation->marks[2]->id], notes: 'Verificado mediante cámaras.');

        $correction = AttendanceCorrection::with('marks')->firstOrFail();
        $this->assertSame(['09:07:00', '22:44:00'], $correction->marks->map(fn ($mark) => $mark->occurred_at->format('H:i:s'))->all());
        $this->assertSame('Verificado mediante cámaras.', $correction->notes);
        $this->assertSame($original, $this->originalSnapshot());
        $this->assertSame(2, AttendanceCorrectionMark::withCount('biometricSources')->get()->sum('biometric_sources_count'));
        $this->assertDatabaseHas('biometric_marks', ['source_text' => '19:38:00']);
    }

    public function test_four_and_six_selected_marks_form_consecutive_pairs(): void
    {
        $this->addMarks('2026-07-17', ['09:00:00', '12:00:00', '13:00:00', '18:00:00']);
        $four = $this->interpretation('2026-07-17');
        $this->postCorrection($four, $four->marks->pluck('id')->all());
        $this->assertSame(4, AttendanceCorrection::firstOrFail()->marks()->count());

        $this->addMarks('2026-07-18', ['08:00:00', '10:00:00', '12:00:00', '15:00:00', '17:00:00', '20:00:00']);
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
        $six = $this->interpretation('2026-07-18');
        $this->postCorrection($six, $six->marks->pluck('id')->all());
        $this->assertSame(6, AttendanceCorrection::whereDate('work_date', '2026-07-18')->firstOrFail()->marks()->count());
    }

    public function test_single_exit_can_be_completed_with_manual_entry(): void
    {
        $this->addMarks('2026-07-17', ['20:30:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $this->postCorrection($interpretation, $interpretation->marks->pluck('id')->all(), ['2026-07-17T08:00']);

        $marks = AttendanceCorrection::firstOrFail()->marks;
        $this->assertSame(['08:00:00', '20:30:00'], $marks->map(fn ($mark) => $mark->occurred_at->format('H:i:s'))->all());
        $this->assertSame(AttendanceCorrectionMark::SOURCE_MANUAL, $marks->first()->source_type);
        $this->assertSame($this->administrator->id, $marks->first()->added_by);
        $this->assertNull($marks->first()->biometric_mark_id);
    }

    public function test_single_entry_can_be_completed_with_manual_exit(): void
    {
        $this->addMarks('2026-07-17', ['08:00:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $this->postCorrection($interpretation, $interpretation->marks->pluck('id')->all(), ['2026-07-17T18:30']);

        $this->assertSame(['08:00:00', '18:30:00'], AttendanceCorrection::firstOrFail()->marks->map(fn ($mark) => $mark->occurred_at->format('H:i:s'))->all());
    }

    public function test_early_morning_mark_remains_assigned_to_previous_work_date(): void
    {
        $this->addMarks('2026-07-17', ['10:00:00']);
        $this->addMarks('2026-07-18', ['04:56:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $this->postCorrection($interpretation, $interpretation->marks->pluck('id')->all());

        $this->assertSame('2026-07-17', AttendanceCorrection::firstOrFail()->work_date->toDateString());
        $this->assertSame('2026-07-18 04:56:00', AttendanceCorrection::firstOrFail()->marks->last()->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_compatible_interpretation_can_be_corrected_then_corrected_again_and_undone(): void
    {
        $this->addMarks('2026-07-17', ['09:00:00', '12:00:00', '14:00:00', '18:00:00']);
        $interpretation = $this->interpretation('2026-07-17');
        $this->assertSame(AttendanceInterpretation::STATUS_COMPLETE, $interpretation->status);

        $this->postCorrection($interpretation, [$interpretation->marks[0]->id, $interpretation->marks[3]->id]);
        $this->postCorrection($interpretation, $interpretation->marks->pluck('id')->all());

        $this->assertSame(2, AttendanceCorrection::count());
        $this->assertNotNull(AttendanceCorrection::oldest('id')->first()->superseded_at);
        $active = AttendanceCorrection::latest('id')->firstOrFail();
        $this->assertNull($active->undone_at);

        $this->actingAs($this->administrator)->delete(route('attendance-corrections.destroy', $interpretation))->assertSessionHasNoErrors();
        $this->assertNotNull($active->fresh()->undone_at);
        $this->assertNull($interpretation->activeCorrection());
        $this->assertSame(AttendanceInterpretation::STATUS_COMPLETE, $interpretation->fresh()->status);
    }

    public function test_invalid_odd_duplicate_and_wrong_work_date_corrections_are_rejected(): void
    {
        $this->addMarks('2026-07-17', ['09:00:00', '12:00:00', '18:00:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $this->postCorrection($interpretation, $interpretation->marks->pluck('id')->all())->assertSessionHasErrors('selected_marks');
        $this->postCorrection($interpretation, [$interpretation->marks[0]->id], ['2026-07-17T09:00'])->assertSessionHasErrors('selected_marks');
        $this->postCorrection($interpretation, [$interpretation->marks[0]->id], ['2026-07-18T08:00'])->assertSessionHasErrors('manual_marks.0');
        $this->assertSame(0, AttendanceCorrection::count());
    }

    public function test_non_administrator_cannot_list_correct_or_undo(): void
    {
        $this->addMarks('2026-07-17', ['09:00:00', '18:00:00']);
        $interpretation = $this->interpretation('2026-07-17');
        $viewer = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($viewer)->get(route('attendance-corrections.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('attendance-corrections.store', $interpretation), ['selected_marks' => $interpretation->marks->pluck('id')->all()])->assertForbidden();
        $this->actingAs($viewer)->delete(route('attendance-corrections.destroy', $interpretation))->assertForbidden();
    }

    public function test_navigation_moves_from_period_to_person_to_calendar_and_modal(): void
    {
        $this->addMarks('2026-07-17', ['09:07:00', '19:38:00', '22:44:00']);
        $this->addMarks('2026-07-18', ['09:00:00', '18:00:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $period = $this->import->controlPeriod;
        $this->actingAs($this->administrator)->get(route('attendance-corrections.index'))
            ->assertOk()->assertSee('Julio 2026')->assertSee('1 jornada requiere revisión');
        $this->get(route('attendance-corrections.index', ['control_period_id' => $period->id]))
            ->assertOk()->assertSee('Selecciona un colaborador')->assertSee('Jusepe');
        $calendarUrl = route('attendance-corrections.index', ['control_period_id' => $period->id, 'person_id' => $this->person->id]);
        $this->get($calendarUrl)->assertOk()->assertSee('Jusepe — Julio 2026')
            ->assertSee('Requiere revisión')->assertSee('Marcaciones biométricas')
            ->assertSee('Agregar marcación faltante')->assertSee('09:07')
            ->assertSee('09:00 · 18:00')
            ->assertSee('Regla de madrugada:')
            ->assertDontSee('Esta marcación corresponde a:')
            ->assertSee('border-amber-400', false)->assertSee('border-emerald-300', false);

        $this->postCorrection($interpretation, [$interpretation->marks[0]->id, $interpretation->marks[2]->id]);
        $this->get($calendarUrl)->assertOk()->assertSee('Corregida')->assertSee('border-blue-300', false);
    }

    public function test_simple_manual_mark_accepts_0559_rejects_0600_and_reopens_modal_with_a_clear_error(): void
    {
        $this->addMarks('2026-07-17', ['20:00:00']);
        $interpretation = $this->interpretation('2026-07-17');
        $url = route('attendance-corrections.index', ['control_period_id' => $this->import->control_period_id, 'person_id' => $this->person->id]);

        $this->actingAs($this->administrator)->from($url)->post(route('attendance-corrections.store', $interpretation), [
            'selected_marks' => $interpretation->marks->pluck('id')->all(),
            'manual_mark_times' => ['06:00'], 'manual_mark_days' => ['next_morning'],
            'correction_interpretation_id' => $interpretation->id,
            'notes' => 'Conservar esta observación',
        ])->assertRedirect($url)->assertSessionHasErrors('manual_mark_times.0');
        $this->get($url)->assertOk()->assertSee('Las horas desde las 06:00 pertenecen al siguiente d\\u00eda', false)
            ->assertSee('Conservar esta observaci\\u00f3n', false)->assertSee('"open":true', false);

        $this->actingAs($this->administrator)->from($url)->post(route('attendance-corrections.store', $interpretation), [
            'selected_marks' => $interpretation->marks->pluck('id')->all(),
            'manual_mark_times' => ['05:59'],
            'correction_interpretation_id' => $interpretation->id,
        ])->assertRedirect($url)->assertSessionHasNoErrors();
        $manual = AttendanceCorrection::firstOrFail()->marks->firstWhere('source_type', AttendanceCorrectionMark::SOURCE_MANUAL);
        $this->assertSame('2026-07-18 05:59:00', $manual->occurred_at->format('Y-m-d H:i:s'));
    }

    public function test_manual_time_automatically_distinguishes_early_morning_from_the_work_day(): void
    {
        $this->addMarks('2026-07-17', ['20:00:00']);
        $interpretation = $this->interpretation('2026-07-17');

        $this->actingAs($this->administrator)->post(route('attendance-corrections.store', $interpretation), [
            'selected_marks' => $interpretation->marks->pluck('id')->all(),
            'manual_mark_times' => ['03:00'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_correction_marks', ['occurred_at' => '2026-07-18 03:00:00', 'source_type' => AttendanceCorrectionMark::SOURCE_MANUAL]);

        $this->actingAs($this->administrator)->post(route('attendance-corrections.store', $interpretation), [
            'selected_marks' => $interpretation->marks->pluck('id')->all(),
            'manual_mark_times' => ['06:00'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_correction_marks', ['occurred_at' => '2026-07-17 06:00:00', 'source_type' => AttendanceCorrectionMark::SOURCE_MANUAL]);
    }

    private function addMarks(string $date, array $times): void
    {
        $day = $this->person->days()->create(['mark_date' => $date, 'original_value' => implode('', $times)]);
        foreach ($times as $index => $time) {
            $day->marks()->create(['marked_time' => $time, 'sequence' => $index + 1, 'source_text' => $time]);
        }
    }

    private function interpretation(string $date): AttendanceInterpretation
    {
        app(AttendanceInterpretationEngine::class)->interpret($this->import);

        return AttendanceInterpretation::whereDate('work_date', $date)->with('marks')->firstOrFail();
    }

    private function postCorrection(AttendanceInterpretation $interpretation, array $selected, array $manual = [], ?string $notes = null)
    {
        return $this->actingAs($this->administrator)->post(route('attendance-corrections.store', $interpretation), [
            'selected_marks' => $selected,
            'manual_marks' => $manual,
            'notes' => $notes,
        ]);
    }

    private function originalSnapshot(): string
    {
        return hash('sha256', $this->person->days()->with('marks')->get()->toJson());
    }
}
