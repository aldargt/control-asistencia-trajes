<?php

namespace Tests\Feature;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceInterpretation;
use App\Models\BiometricImport;
use App\Models\BiometricImportPerson;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Models\EmploymentCondition;
use App\Models\User;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceCorrectionService;
use App\Services\AttendanceInterpretationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AttendanceCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private ControlPeriod $period;

    private BiometricImport $import;

    private Collaborator $collaborator;

    private BiometricImportPerson $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = User::factory()->create();
        $this->collaborator = Collaborator::factory()->create(['hire_date' => '2020-01-01']);
        EmploymentCondition::factory()->create(['collaborator_id' => $this->collaborator->id, 'job_role_id' => $this->collaborator->job_role_id, 'weekly_hours' => 30, 'effective_from' => '2020-01-01', 'effective_to' => null, 'created_by' => $this->administrator->id]);
        $this->period = ControlPeriod::create(['year' => 2026, 'month' => 7, 'reference_days' => 26, 'created_by' => $this->administrator->id]);
        $this->import = BiometricImport::create(['control_period_id' => $this->period->id, 'imported_by' => $this->administrator->id, 'original_filename' => 'julio.xls', 'stored_path' => 'biometric-imports/julio.xls', 'file_size' => 100, 'sha256' => str_repeat('a', 64), 'imported_at' => now()]);
        $this->person = $this->import->people()->create(['collaborator_id' => $this->collaborator->id, 'source_biometric_id' => '20', 'source_name' => 'Jusepe', 'source_row' => 5]);
    }

    public static function intervalCases(): array
    {
        return [
            'two marks' => [['08:00:00', '17:00:00'], 540],
            'four marks' => [['08:00:00', '12:00:00', '13:00:00', '17:00:00'], 480],
            'six marks' => [['08:00:00', '10:00:00', '12:00:00', '15:00:00', '17:00:00', '20:00:00'], 480],
        ];
    }

    #[DataProvider('intervalCases')]
    public function test_any_valid_number_of_pairs_is_calculated_in_minutes(array $times, int $expected): void
    {
        $interpretation = $this->interpret('2026-07-17', $times);
        $interpretation->update(['status' => AttendanceInterpretation::STATUS_COMPLETE]);
        $result = app(AttendanceCalculationService::class)->calculateDay($interpretation->fresh('marks'));
        $this->assertSame($expected, $result['day']['recognized_minutes']);
        $this->assertCount(count($times) / 2, $result['intervals']);
    }

    public function test_night_interval_uses_assigned_datetimes_and_crosses_midnight(): void
    {
        $this->addMarks('2026-07-17', ['10:05:00', '18:00:00', '21:49:00']);
        $this->addMarks('2026-07-18', ['04:56:00']);
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
        $interpretation = AttendanceInterpretation::whereDate('work_date', '2026-07-17')->with('marks')->firstOrFail();
        $result = app(AttendanceCalculationService::class)->calculateDay($interpretation);
        $this->assertSame(902, $result['day']['recognized_minutes']);
        $this->assertSame(427, $result['intervals'][1]['minutes']);
    }

    public function test_odd_and_single_mark_days_are_pending_without_definitive_minutes(): void
    {
        foreach ([['08:30:00'], ['08:30:00', '12:00:00', '15:00:00']] as $index => $times) {
            $interpretation = $this->interpret('2026-07-'.(17 + $index), $times);
            $result = app(AttendanceCalculationService::class)->calculateDay($interpretation);
            $this->assertSame(AttendanceCalculationDay::STATUS_PENDING, $result['day']['status']);
            $this->assertNull($result['day']['recognized_minutes']);
            $this->assertEmpty($result['intervals']);
        }
    }

    public function test_latest_correction_excludes_unselected_mark_and_recalculation_is_idempotent(): void
    {
        $interpretation = $this->interpret('2026-07-17', ['09:07:00', '19:38:00', '22:44:00']);
        $service = app(AttendanceCorrectionService::class);
        $service->correct($interpretation, $this->administrator, ['selected_marks' => [$interpretation->marks[0]->id, $interpretation->marks[2]->id]]);
        $calculator = app(AttendanceCalculationService::class);
        $calculator->calculate($this->period);
        $first = AttendanceCalculation::firstOrFail();
        $this->assertSame(817, $first->recognized_minutes);
        $this->assertSame(7800, $first->expected_minutes);

        $calculator->calculate($this->period);
        $this->assertSame(1, AttendanceCalculation::count());
        $this->assertSame(1, AttendanceCalculationDay::count());

        $service->correct($interpretation, $this->administrator, ['selected_marks' => [$interpretation->marks[0]->id, $interpretation->marks[1]->id]]);
        $this->assertSame(817, $first->fresh()->recognized_minutes);
        $calculator->calculate($this->period);
        $this->assertSame(631, $first->fresh()->recognized_minutes);
        $this->assertSame(1, AttendanceCalculationDay::count());
        $this->assertSame(2, AttendanceCorrection::count());
    }

    public function test_interface_groups_people_without_marks_and_shows_calendar_and_persisted_interval_detail(): void
    {
        $this->addMarks('2026-07-02', ['08:46:00', '15:48:00']);
        $withoutMarks = Collaborator::factory()->create(['hire_date' => '2020-01-01']);
        $personWithoutMarks = $this->import->people()->create(['collaborator_id' => $withoutMarks->id, 'source_biometric_id' => '21', 'source_name' => 'Sin marcas', 'source_row' => 6]);
        $personWithoutMarks->days()->create(['mark_date' => '2026-07-02', 'original_value' => null]);
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
        app(AttendanceCalculationService::class)->calculate($this->period);

        $active = AttendanceCalculation::where('biometric_import_person_id', $this->person->id)->firstOrFail();
        $this->actingAs($this->administrator)->get(route('attendance-calculations.index', ['control_period_id' => $this->period->id]))
            ->assertOk()->assertSee('Colaboradores con actividad')->assertSee('Ver colaboradores sin marcaciones (1)')->assertSee($withoutMarks->full_name);
        $this->actingAs($this->administrator)->get(route('attendance-calculations.index', ['control_period_id' => $this->period->id, 'calculation_id' => $active->id]))
            ->assertOk()->assertSeeInOrder(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])
            ->assertSee('7 h 02 min')->assertSee('08:46 → 15:48')->assertSee('data-calculation-day-open', false);
    }

    public function test_pending_calendar_day_links_to_the_exact_inconsistency_without_recalculating(): void
    {
        $this->interpret('2026-07-07', ['08:30:00']);
        app(AttendanceCalculationService::class)->calculate($this->period);
        $calculation = AttendanceCalculation::firstOrFail();
        $day = $calculation->days()->firstOrFail();

        $response = $this->actingAs($this->administrator)->get(route('attendance-calculations.index', ['control_period_id' => $this->period->id, 'calculation_id' => $calculation->id]));
        $response->assertOk()->assertSee('Requiere revisión')->assertSee(route('attendance-corrections.index', ['control_period_id' => $this->period->id, 'person_id' => $this->person->id, 'interpretation_id' => $day->attendance_interpretation_id]));
        $this->actingAs($this->administrator)->get(route('attendance-corrections.index', ['control_period_id' => $this->period->id, 'person_id' => $this->person->id, 'interpretation_id' => $day->attendance_interpretation_id]))
            ->assertOk()->assertSee('"open":true', false);
        $this->assertSame(1, AttendanceCalculation::count());
        $this->assertSame(0, $calculation->fresh()->recognized_minutes);
    }

    public function test_period_card_summarizes_each_attendance_day_state_without_duplicates(): void
    {
        $this->addMarks('2026-07-02', ['08:00:00', '17:00:00']);
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
        app(AttendanceCalculationService::class)->calculate($this->period);
        $calculation = AttendanceCalculation::firstOrFail();

        foreach ([
            ['2026-07-03', AttendanceCalculationDay::STATUS_RECOGNIZED, AttendanceCalculationDay::SOURCE_CORRECTION, 480],
            ['2026-07-04', AttendanceCalculationDay::STATUS_PENDING, AttendanceCalculationDay::SOURCE_AUTOMATIC, null],
            ['2026-07-05', AttendanceCalculationDay::STATUS_NO_MARKS, null, null],
        ] as [$date, $status, $source, $minutes]) {
            $interpretation = $this->person->attendanceInterpretations()->create(['work_date' => $date, 'status' => 'complete', 'original_marks_count' => 0, 'logical_marks_count' => 0, 'duplicate_marks_count' => 0, 'interpreted_at' => now()]);
            $calculation->days()->create(['attendance_interpretation_id' => $interpretation->id, 'work_date' => $date, 'status' => $status, 'source_type' => $source, 'recognized_minutes' => $minutes]);
        }

        $this->actingAs($this->administrator)->get(route('attendance-calculations.index'))
            ->assertOk()->assertSee('>1</span> compatibles', false)->assertSee('>1</span> corregidas', false)
            ->assertSee('>1</span> requieren revisión', false)->assertSee('>1</span> sin marcaciones', false);
    }

    #[DataProvider('expectedMinuteCases')]
    public function test_expected_minutes_follow_weekly_hours_divided_by_six(int $weeklyHours, int $days, int $expected): void
    {
        $this->collaborator->employmentConditions()->update(['weekly_hours' => $weeklyHours]);
        $this->period->update(['reference_days' => $days]);
        app(AttendanceCalculationService::class)->calculate($this->period);
        $this->assertSame($expected, AttendanceCalculation::firstOrFail()->expected_minutes);
    }

    public static function expectedMinuteCases(): array
    {
        return [[30, 26, 7800], [30, 24, 7200], [30, 15, 4500], [60, 26, 15600]];
    }

    #[DataProvider('comparisonCases')]
    public function test_period_comparison_preserves_signed_minute_difference(int $recognized, string $expectedStatus, int $difference): void
    {
        $dailyMinutes = intdiv($recognized, 10);
        $endHour = 6 + intdiv($dailyMinutes, 60);
        $endMinute = $dailyMinutes % 60;
        foreach (range(1, 10) as $day) {
            $this->addMarks('2026-07-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), ['06:00:00', sprintf('%02d:%02d:00', $endHour, $endMinute)]);
        }
        app(AttendanceInterpretationEngine::class)->interpret($this->import);
        app(AttendanceCalculationService::class)->calculate($this->period);
        $calculation = AttendanceCalculation::firstOrFail();
        $this->assertSame($difference, $calculation->difference_minutes);
        $actualStatus = $difference === 0 ? 'compliance' : ($difference < 0 ? 'deficit' : 'surplus');
        $this->assertSame($expectedStatus, $actualStatus);
        $this->assertSame($expectedStatus, $calculation->balance_status);
    }

    public static function comparisonCases(): array
    {
        return [[7800, 'compliance', 0], [7500, 'deficit', -300], [8100, 'surplus', 300]];
    }

    private function interpret(string $date, array $times): AttendanceInterpretation
    {
        $this->addMarks($date, $times);
        app(AttendanceInterpretationEngine::class)->interpret($this->import);

        return AttendanceInterpretation::whereDate('work_date', $date)->with('marks')->firstOrFail();
    }

    private function addMarks(string $date, array $times): void
    {
        $day = $this->person->days()->create(['mark_date' => $date, 'original_value' => implode(' ', $times)]);
        foreach ($times as $index => $time) {
            $day->marks()->create(['marked_time' => $time, 'sequence' => $index + 1, 'source_text' => $time]);
        }
    }
}
