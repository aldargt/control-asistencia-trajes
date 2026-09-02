<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Models\EmploymentCondition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControlPeriodManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_manage_control_periods(): void
    {
        $this->get(route('control-periods.index'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['role' => 'viewer']))
            ->get(route('control-periods.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->get(route('control-periods.index'))->assertOk()->assertSee('Períodos de control');
    }

    public function test_administrator_can_create_and_edit_reference_days(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs($administrator)->post(route('control-periods.store'), [
            'year' => 2026,
            'month' => 2,
            'reference_days' => 26,
        ])->assertRedirectToRoute('control-periods.index');

        $period = ControlPeriod::firstOrFail();
        $this->assertSame(26, $period->reference_days);
        $this->assertSame($administrator->id, $period->created_by);

        $this->actingAs($administrator)->put(route('control-periods.update', $period), [
            'year' => 2026,
            'month' => 2,
            'reference_days' => 24,
        ])->assertRedirectToRoute('control-periods.index');

        $this->assertSame(24, $period->fresh()->reference_days);
        $this->assertSame($administrator->id, $period->fresh()->updated_by);
    }

    public function test_month_and_year_are_unique_and_reference_days_are_reasonable(): void
    {
        $administrator = User::factory()->create();
        ControlPeriod::create(['year' => 2026, 'month' => 2, 'reference_days' => 26, 'created_by' => $administrator->id]);

        $this->actingAs($administrator)->post(route('control-periods.store'), [
            'year' => 2026,
            'month' => 2,
            'reference_days' => 0,
        ])->assertSessionHasErrors(['month', 'reference_days']);
    }

    public function test_expected_hour_formula_uses_six_reference_days_without_daily_distribution(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        $condition = EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'weekly_hours' => 30,
            'monthly_salary' => 2500,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'created_by' => $administrator,
        ]);
        $collaborator->load('employmentConditions');

        foreach ([26 => 130, 24 => 120, 15 => 75] as $days => $expected) {
            $period = new ControlPeriod(['year' => 2026, 'month' => 1, 'reference_days' => $days]);
            $reference = $period->hourReferenceFor($collaborator);

            $this->assertSame(5.0, $reference['daily_reference_hours']);
            $this->assertSame((float) $expected, $reference['expected_hours']);
        }

        $this->assertSame('2500.00', $condition->fresh()->monthly_salary);
        $this->assertFalse(Schema::hasTable('work_schedules'));
        $this->assertFalse(Schema::hasTable('work_schedule_days'));
        $this->assertFalse(Schema::hasTable('work_schedule_intervals'));
    }

    public function test_formula_supports_60_and_66_weekly_hours(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        $condition = EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'weekly_hours' => 60,
            'effective_from' => '2025-01-01',
            'created_by' => $administrator,
        ]);
        $period = new ControlPeriod(['year' => 2026, 'month' => 1, 'reference_days' => 26]);

        $collaborator->load('employmentConditions');
        $this->assertSame(260.0, $period->hourReferenceFor($collaborator)['expected_hours']);

        $condition->update(['weekly_hours' => 66]);
        $collaborator->load('employmentConditions');
        $this->assertSame(286.0, $period->hourReferenceFor($collaborator)['expected_hours']);
    }

    public function test_condition_validity_selects_the_correct_hours_for_each_month(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        EmploymentCondition::factory()->create(['collaborator_id' => $collaborator, 'weekly_hours' => 30, 'effective_from' => '2026-01-01', 'effective_to' => '2026-01-31', 'created_by' => $administrator]);
        EmploymentCondition::factory()->create(['collaborator_id' => $collaborator, 'weekly_hours' => 60, 'effective_from' => '2026-02-01', 'effective_to' => null, 'created_by' => $administrator]);
        $collaborator->load('employmentConditions');

        $january = new ControlPeriod(['year' => 2026, 'month' => 1, 'reference_days' => 26]);
        $february = new ControlPeriod(['year' => 2026, 'month' => 2, 'reference_days' => 24]);

        $this->assertSame(130.0, $january->hourReferenceFor($collaborator)['expected_hours']);
        $this->assertSame(240.0, $february->hourReferenceFor($collaborator)['expected_hours']);
    }

    public function test_multiple_conditions_inside_one_month_are_flagged_instead_of_prorated_arbitrarily(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        EmploymentCondition::factory()->create(['collaborator_id' => $collaborator, 'effective_from' => '2026-01-01', 'effective_to' => '2026-01-15', 'created_by' => $administrator]);
        EmploymentCondition::factory()->create(['collaborator_id' => $collaborator, 'effective_from' => '2026-01-16', 'effective_to' => null, 'created_by' => $administrator]);
        $collaborator->load('employmentConditions');
        $period = new ControlPeriod(['year' => 2026, 'month' => 1, 'reference_days' => 26]);

        $reference = $period->hourReferenceFor($collaborator);

        $this->assertSame('multiple_conditions', $reference['status']);
        $this->assertNull($reference['expected_hours']);
    }
}
