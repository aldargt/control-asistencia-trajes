<?php

namespace Tests\Feature;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
use App\Models\BiometricImport;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Models\EmploymentCondition;
use App\Models\RemunerationCalculation;
use App\Models\User;
use App\Services\RemunerationCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemunerationCalculationTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private ControlPeriod $period;

    private BiometricImport $import;

    private Collaborator $collaborator;

    private $person;

    private EmploymentCondition $condition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->administrator = User::factory()->create();
        $this->collaborator = Collaborator::factory()->create(['hire_date' => '2020-01-01']);
        $this->condition = EmploymentCondition::factory()->create(['collaborator_id' => $this->collaborator->id, 'job_role_id' => $this->collaborator->job_role_id, 'monthly_salary' => 2500, 'weekly_hours' => 30, 'effective_from' => '2020-01-01', 'effective_to' => null, 'created_by' => $this->administrator->id]);
        $this->period = ControlPeriod::create(['year' => 2026, 'month' => 7, 'reference_days' => 26, 'created_by' => $this->administrator->id]);
        $this->import = BiometricImport::create(['control_period_id' => $this->period->id, 'imported_by' => $this->administrator->id, 'original_filename' => 'julio.xls', 'stored_path' => 'biometric-imports/julio.xls', 'file_size' => 100, 'sha256' => str_repeat('a', 64), 'imported_at' => now()]);
        $this->person = $this->import->people()->create(['collaborator_id' => $this->collaborator->id, 'source_biometric_id' => '20', 'source_name' => 'Jusepe', 'source_row' => 5]);
    }

    #[DataProvider('balanceCases')]
    public function test_exact_deficit_and_surplus_calculations(int $recognized, int $deduction, int $increment, int $final): void
    {
        $this->attendance(7800, $recognized);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $result = RemunerationCalculation::firstOrFail();
        $this->assertSame($deduction, $result->deficit_deduction_cents);
        $this->assertSame($increment, $result->surplus_increment_cents);
        $this->assertSame($final, $result->final_amount_cents);
    }

    public static function balanceCases(): array
    {
        return [
            'exact' => [7800, 0, 0, 250000],
            'five hour deficit' => [7500, 9615, 0, 240385],
            'five hour surplus' => [8100, 0, 9615, 259615],
        ];
    }

    #[DataProvider('referenceCases')]
    public function test_weekly_hours_and_reference_days_are_snapshotted_without_changing_salary(int $weekly, int $days, int $expectedMinutes, string $dailyHours): void
    {
        $this->condition->update(['weekly_hours' => $weekly]);
        $this->period->update(['reference_days' => $days]);
        $this->attendance($expectedMinutes, $expectedMinutes);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $result = RemunerationCalculation::firstOrFail();
        $this->assertSame(250000, $result->monthly_salary_cents);
        $this->assertSame($expectedMinutes, $result->expected_minutes);
        $this->assertSame($dailyHours, $result->daily_reference_hours);
    }

    public static function referenceCases(): array
    {
        return [[30, 26, 7800, '5.000000'], [30, 24, 7200, '5.000000'], [35, 24, 8400, '5.833333'], [60, 26, 15600, '10.000000'], [66, 25, 16500, '11.000000']];
    }

    public function test_business_hhmm_conversion_and_unrounded_rate_produce_required_precision(): void
    {
        $this->condition->update(['weekly_hours' => 60]);
        $this->attendance(15600, 15600 - 658);
        $service = app(RemunerationCalculationService::class);
        $this->assertSame(1058, $service->businessDurationHundredths(658));
        $service->calculate($this->period);
        $result = RemunerationCalculation::firstOrFail();
        $this->assertSame(658, $result->deficit_minutes);
        $this->assertSame('9.6153846153', $result->hourly_rate);
        $this->assertSame(10173, $result->deficit_deduction_cents);
        $this->assertSame(239827, $result->final_amount_cents);
    }

    public function test_pending_attendance_blocks_money_and_person_without_marks_gets_no_result(): void
    {
        $pending = $this->attendance(7800, 300, AttendanceCalculation::STATUS_PROVISIONAL, AttendanceCalculationDay::STATUS_PENDING);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $blocked = RemunerationCalculation::firstOrFail();
        $this->assertSame(RemunerationCalculation::STATUS_BLOCKED, $blocked->status);
        $this->assertNull($blocked->final_amount_cents);

        $blocked->delete();
        $pending->update(['status' => AttendanceCalculation::STATUS_COMPLETE]);
        $pending->days()->update(['status' => AttendanceCalculationDay::STATUS_NO_MARKS]);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $this->assertSame(0, RemunerationCalculation::count());
    }

    public function test_recalculation_is_idempotent_and_uses_new_attendance_snapshot(): void
    {
        $attendance = $this->attendance(7800, 7800);
        $service = app(RemunerationCalculationService::class);
        $service->calculate($this->period);
        $id = RemunerationCalculation::firstOrFail()->id;
        $service->calculate($this->period);
        $this->assertSame(1, RemunerationCalculation::count());
        $this->assertSame($id, RemunerationCalculation::first()->id);

        $attendance->update(['recognized_minutes' => 7500, 'difference_minutes' => -300, 'calculated_at' => now()->addSecond()]);
        $this->assertSame(250000, RemunerationCalculation::first()->final_amount_cents);
        $service->calculate($this->period);
        $this->assertSame(240385, RemunerationCalculation::first()->final_amount_cents);
    }

    public function test_condition_change_marks_snapshot_stale_and_requires_consistent_hour_recalculation(): void
    {
        $this->attendance(7800, 7800);
        $service = app(RemunerationCalculationService::class);
        $service->calculate($this->period);
        $old = RemunerationCalculation::firstOrFail();
        $this->condition->update(['monthly_salary' => 3000, 'weekly_hours' => 60]);
        $this->assertTrue($service->isStale($old->fresh(), $this->period->fresh()));
        $this->assertSame(250000, $old->fresh()->final_amount_cents);

        $service->calculate($this->period->fresh());
        $this->assertSame(RemunerationCalculation::STATUS_CONFIGURATION_PENDING, $old->fresh()->status);
        $this->assertNull($old->fresh()->final_amount_cents);

        $attendance = AttendanceCalculation::firstOrFail();
        $attendance->update(['expected_minutes' => 15600, 'recognized_minutes' => 15600, 'difference_minutes' => 0, 'calculated_at' => now()->addSecond()]);
        $service->calculate($this->period->fresh());
        $this->assertSame(RemunerationCalculation::STATUS_CALCULATED, $old->fresh()->status);
        $this->assertSame(300000, $old->fresh()->final_amount_cents);
    }

    public function test_interface_explains_calculation_and_calendar_traceability(): void
    {
        $attendance = $this->attendance(7800, 7500);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $this->actingAs($this->administrator)->get(route('remunerations.index', ['control_period_id' => $this->period->id, 'calculation_id' => $attendance->id]))
            ->assertOk()->assertSee('Salario mensual base')->assertSee('Bs 2.500,00')->assertSee('Deducción por déficit')
            ->assertSee('Remuneración final')->assertSee('Trazabilidad diaria')->assertSeeInOrder(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom']);
    }

    public function test_fresh_and_stale_results_are_presented_consistently_without_automatic_recalculation(): void
    {
        $attendance = $this->attendance(7800, 7800);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $listUrl = route('remunerations.index', ['control_period_id' => $this->period->id]);
        $detailUrl = route('remunerations.index', ['control_period_id' => $this->period->id, 'calculation_id' => $attendance->id]);

        $this->actingAs($this->administrator)->get($listUrl)->assertOk()->assertSee('Bs 2.500,00')->assertSee('Calculada')->assertDontSee('Requiere recálculo');
        $this->condition->update(['monthly_salary' => 3000]);

        $this->get($listUrl)->assertOk()->assertSee('Requiere recálculo')->assertSee('El importe anterior ya no está vigente.')->assertDontSee('Bs 2.500,00');
        $this->get($detailUrl)->assertOk()->assertSee('Resultado desactualizado')->assertDontSee('Remuneración final')->assertDontSee('Bs 2.500,00');
        $this->assertSame(250000, RemunerationCalculation::firstOrFail()->final_amount_cents);

        $this->post(route('remunerations.store', $this->period))->assertRedirect($listUrl);
        $updated = RemunerationCalculation::firstOrFail();
        $this->assertSame(300000, $updated->final_amount_cents);
        $this->assertFalse(app(RemunerationCalculationService::class)->isStale($updated, $this->period->fresh()));
        $this->get($detailUrl)->assertOk()->assertSee('Bs 3.000,00')->assertSee('Remuneración final')->assertDontSee('Resultado desactualizado');
    }

    #[DataProvider('staleConfigurationCases')]
    public function test_relevant_configuration_changes_mark_existing_result_stale(array $conditionChanges, array $periodChanges): void
    {
        $this->attendance(7800, 7800);
        $service = app(RemunerationCalculationService::class);
        $service->calculate($this->period);
        $result = RemunerationCalculation::firstOrFail();

        if ($conditionChanges !== []) {
            $this->condition->update($conditionChanges);
        }
        if ($periodChanges !== []) {
            $this->period->update($periodChanges);
        }

        $this->assertTrue($service->isStale($result->fresh(), $this->period->fresh()));
        $this->assertSame(250000, $result->fresh()->final_amount_cents);
    }

    public static function staleConfigurationCases(): array
    {
        return [
            'salary' => [['monthly_salary' => 2600], []],
            'weekly hours' => [['weekly_hours' => 60], []],
            'reference days' => [[], ['reference_days' => 24]],
        ];
    }

    public function test_large_allowed_values_do_not_overflow_or_use_float_arithmetic(): void
    {
        $this->condition->update(['monthly_salary' => '9999999999.99', 'weekly_hours' => 60]);
        $this->attendance(15600, 3600);

        app(RemunerationCalculationService::class)->calculate($this->period);
        $result = RemunerationCalculation::firstOrFail();
        $this->assertSame(12000, $result->deficit_minutes);
        $this->assertSame(20000, $result->valued_duration_hundredths);
        $this->assertSame(769230769230, $result->deficit_deduction_cents);
        $this->assertSame(230769230769, $result->final_amount_cents);
    }

    public function test_remuneration_routes_enforce_existing_administrator_permission(): void
    {
        $this->attendance(7800, 7800);
        $viewer = User::factory()->create(['role' => 'viewer']);
        $index = route('remunerations.index', ['control_period_id' => $this->period->id]);
        $store = route('remunerations.store', $this->period);

        $this->actingAs($viewer)->get($index)->assertForbidden();
        $this->actingAs($viewer)->post($store)->assertForbidden();
        $this->assertSame(0, RemunerationCalculation::count());

        $this->actingAs($this->administrator)->get($index)->assertOk();
        $this->actingAs($this->administrator)->post($store)->assertRedirect($index);
        $this->assertSame(1, RemunerationCalculation::count());
    }

    public function test_period_card_combines_calculated_blocked_configuration_stale_and_pending_states(): void
    {
        $this->attendance(7800, 7800);
        app(RemunerationCalculationService::class)->calculate($this->period);
        $this->additionalAttendance('Bloqueado', AttendanceCalculation::STATUS_PROVISIONAL, RemunerationCalculation::STATUS_BLOCKED);
        $this->additionalAttendance('Configuración', AttendanceCalculation::STATUS_COMPLETE, RemunerationCalculation::STATUS_CONFIGURATION_PENDING);
        $this->additionalAttendance('Sin cálculo', AttendanceCalculation::STATUS_COMPLETE);
        $stale = $this->additionalAttendance('Desactualizado', AttendanceCalculation::STATUS_COMPLETE, RemunerationCalculation::STATUS_CALCULATED);
        $stale->collaborator->employmentConditions()->update(['monthly_salary' => 2600]);
        $this->additionalAttendance('Sin marcas', AttendanceCalculation::STATUS_COMPLETE, null, AttendanceCalculationDay::STATUS_NO_MARKS);

        $this->actingAs($this->administrator)->get(route('remunerations.index'))
            ->assertOk()->assertSee('>1</span> calculadas', false)->assertSee('>1</span> requieren revisión', false)
            ->assertSee('>1</span> con configuración pendiente', false)->assertSee('>1</span> requieren recálculo', false)
            ->assertSee('>1</span> pendientes de cálculo', false)->assertSee('>1 sin marcaciones</p>', false);
    }

    private function attendance(int $expected, int $recognized, string $status = AttendanceCalculation::STATUS_COMPLETE, string $dayStatus = AttendanceCalculationDay::STATUS_RECOGNIZED): AttendanceCalculation
    {
        $calculation = AttendanceCalculation::create(['control_period_id' => $this->period->id, 'biometric_import_person_id' => $this->person->id, 'collaborator_id' => $this->collaborator->id, 'status' => $status, 'balance_status' => $recognized < $expected ? 'deficit' : ($recognized > $expected ? 'surplus' : 'compliance'), 'expected_minutes' => $expected, 'recognized_minutes' => $recognized, 'difference_minutes' => $recognized - $expected, 'pending_days' => $status === AttendanceCalculation::STATUS_PROVISIONAL ? 1 : 0, 'no_marks_days' => 0, 'calculated_at' => now()]);
        $interpretation = $this->person->attendanceInterpretations()->create(['work_date' => '2026-07-01', 'status' => 'complete', 'original_marks_count' => 2, 'logical_marks_count' => 2, 'duplicate_marks_count' => 0, 'interpreted_at' => now()]);
        $calculation->days()->create(['attendance_interpretation_id' => $interpretation->id, 'work_date' => '2026-07-01', 'status' => $dayStatus, 'source_type' => 'automatic', 'recognized_minutes' => $dayStatus === AttendanceCalculationDay::STATUS_RECOGNIZED ? $recognized : null]);

        return $calculation;
    }

    private function additionalAttendance(string $name, string $attendanceStatus, ?string $remunerationStatus = null, string $dayStatus = AttendanceCalculationDay::STATUS_RECOGNIZED): AttendanceCalculation
    {
        $collaborator = Collaborator::factory()->create(['full_name' => $name, 'hire_date' => '2020-01-01']);
        $condition = EmploymentCondition::factory()->create(['collaborator_id' => $collaborator->id, 'job_role_id' => $collaborator->job_role_id, 'monthly_salary' => 2500, 'weekly_hours' => 30, 'effective_from' => '2020-01-01', 'effective_to' => null, 'created_by' => $this->administrator->id]);
        $person = $this->import->people()->create(['collaborator_id' => $collaborator->id, 'source_biometric_id' => (string) (20 + $this->import->people()->count()), 'source_name' => $name, 'source_row' => 5 + $this->import->people()->count()]);
        $calculation = AttendanceCalculation::create(['control_period_id' => $this->period->id, 'biometric_import_person_id' => $person->id, 'collaborator_id' => $collaborator->id, 'status' => $attendanceStatus, 'balance_status' => 'compliance', 'expected_minutes' => 7800, 'recognized_minutes' => 7800, 'difference_minutes' => 0, 'pending_days' => $attendanceStatus === AttendanceCalculation::STATUS_PROVISIONAL ? 1 : 0, 'no_marks_days' => $dayStatus === AttendanceCalculationDay::STATUS_NO_MARKS ? 1 : 0, 'calculated_at' => now()]);
        $interpretation = $person->attendanceInterpretations()->create(['work_date' => '2026-07-01', 'status' => 'complete', 'original_marks_count' => 2, 'logical_marks_count' => 2, 'duplicate_marks_count' => 0, 'interpreted_at' => now()]);
        $calculation->days()->create(['attendance_interpretation_id' => $interpretation->id, 'work_date' => '2026-07-01', 'status' => $dayStatus, 'source_type' => $dayStatus === AttendanceCalculationDay::STATUS_NO_MARKS ? null : AttendanceCalculationDay::SOURCE_AUTOMATIC, 'recognized_minutes' => $dayStatus === AttendanceCalculationDay::STATUS_NO_MARKS ? null : 7800]);

        if ($remunerationStatus) {
            $calculation->remuneration()->create(['control_period_id' => $this->period->id, 'biometric_import_person_id' => $person->id, 'collaborator_id' => $collaborator->id, 'employment_condition_id' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? $condition->id : null, 'status' => $remunerationStatus, 'monthly_salary_cents' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 250000 : null, 'weekly_hours_hundredths' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 3000 : null, 'reference_days' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 26 : null, 'daily_reference_hours' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 5 : null, 'expected_minutes' => 7800, 'recognized_minutes' => 7800, 'difference_minutes' => 0, 'deficit_minutes' => 0, 'surplus_minutes' => 0, 'valued_duration_hundredths' => 0, 'hourly_rate' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? '19.2307692307' : null, 'base_amount_cents' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 250000 : null, 'deficit_deduction_cents' => 0, 'surplus_increment_cents' => 0, 'final_amount_cents' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? 250000 : null, 'source_attendance_calculated_at' => $calculation->calculated_at, 'source_condition_updated_at' => $condition->updated_at, 'calculated_at' => $remunerationStatus === RemunerationCalculation::STATUS_CALCULATED ? now() : null]);
        }

        return $calculation->load('collaborator.employmentConditions');
    }
}
