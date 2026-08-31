<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\CollaboratorActivityPeriod;
use App\Models\EmploymentCondition;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CollaboratorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_collaborators(): void
    {
        $this->get(route('collaborators.index'))->assertRedirectToRoute('login');
    }

    public function test_administrative_user_can_access_collaborators(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs($administrator)
            ->get(route('collaborators.index'))
            ->assertOk()
            ->assertSee('Colaboradores');
    }

    public function test_list_exposes_clear_quick_actions(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create();

        $this->actingAs($administrator)->get(route('collaborators.index'))
            ->assertOk()
            ->assertSee('Ver detalle')
            ->assertSee('Nueva condición')
            ->assertSee('Desactivar')
            ->assertSee(route('collaborators.toggle-status', $collaborator));
    }

    public function test_administrator_can_toggle_status_from_collaborator_list_and_preserve_activity_history(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['is_active' => true, 'hire_date' => '2026-01-01']);
        CollaboratorActivityPeriod::create([
            'collaborator_id' => $collaborator->id,
            'started_at' => $collaborator->hire_date,
            'changed_by' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->patch(route('collaborators.toggle-status', $collaborator))
            ->assertRedirectToRoute('collaborators.index')
            ->assertSessionHas('success', 'Colaborador desactivado correctamente.');

        $collaborator->refresh();
        $this->assertFalse($collaborator->is_active);
        $this->assertNotNull($collaborator->activityPeriods()->firstOrFail()->ended_at);

        Carbon::setTestNow(today()->addDays(3));
        $this->actingAs($administrator)
            ->patch(route('collaborators.toggle-status', $collaborator))
            ->assertRedirectToRoute('collaborators.index')
            ->assertSessionHas('success', 'Colaborador activado correctamente.');

        $this->assertTrue($collaborator->fresh()->is_active);
        $this->assertCount(2, $collaborator->activityPeriods()->get());
        Carbon::setTestNow();
    }

    public function test_non_administrator_cannot_access_collaborators(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($user)
            ->get(route('collaborators.index'))
            ->assertForbidden();
    }

    public function test_administrator_can_create_collaborator_with_initial_condition(): void
    {
        $administrator = User::factory()->create();
        $jobRole = JobRole::factory()->create(['name' => 'Ayudante']);

        $this->actingAs($administrator)
            ->post(route('collaborators.store'), $this->validPayload($jobRole))
            ->assertRedirectToRoute('collaborators.index')
            ->assertSessionHas('success', 'Colaborador creado correctamente.');

        $collaborator = Collaborator::where('biometric_id', 37)->firstOrFail();
        $condition = $collaborator->employmentConditions()->firstOrFail();

        $this->assertSame('María Flores', $collaborator->full_name);
        $this->assertSame($jobRole->id, $collaborator->job_role_id);
        $this->assertTrue($collaborator->is_active);
        $this->assertSame('2500.00', $condition->monthly_salary);
        $this->assertSame('30.00', $condition->weekly_hours);
        $this->assertTrue($condition->creator->is($administrator));
        $this->assertSame($jobRole->id, $condition->job_role_id);
        $this->assertCount(1, $collaborator->activityPeriods);
    }

    public function test_biometric_id_is_required_and_occupation_status_is_validated(): void
    {
        $administrator = User::factory()->create();
        $jobRole = JobRole::factory()->create();
        $payload = $this->validPayload($jobRole);
        $payload['biometric_id'] = '';
        $payload['occupation_status'] = 'invalid';

        $this->actingAs($administrator)->post(route('collaborators.store'), $payload)
            ->assertSessionHasErrors(['biometric_id', 'occupation_status']);
    }

    public function test_seniority_excludes_inactive_periods(): void
    {
        Carbon::setTestNow('2026-01-31');
        $collaborator = Collaborator::factory()->create(['hire_date' => '2026-01-01']);
        CollaboratorActivityPeriod::create(['collaborator_id' => $collaborator->id, 'started_at' => '2026-01-01', 'ended_at' => '2026-01-10']);
        CollaboratorActivityPeriod::create(['collaborator_id' => $collaborator->id, 'started_at' => '2026-01-21']);

        $this->assertSame(21, $collaborator->active_days);
        $this->assertSame('0 meses', $collaborator->seniority);
        Carbon::setTestNow();
    }

    public function test_biometric_id_and_identity_document_must_be_unique(): void
    {
        $administrator = User::factory()->create();
        $jobRole = JobRole::factory()->create();
        Collaborator::factory()->create([
            'identity_document' => 'CI-123',
            'biometric_id' => 37,
        ]);

        $this->actingAs($administrator)
            ->post(route('collaborators.store'), $this->validPayload($jobRole))
            ->assertSessionHasErrors(['identity_document', 'biometric_id']);
    }

    public function test_inactive_job_role_cannot_be_assigned_to_new_collaborator(): void
    {
        $administrator = User::factory()->create();
        $jobRole = JobRole::factory()->create(['is_active' => false]);

        $this->actingAs($administrator)
            ->post(route('collaborators.store'), $this->validPayload($jobRole))
            ->assertSessionHasErrors('job_role_id');
    }

    public function test_editing_preserves_status_and_status_action_can_save_an_observation(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        CollaboratorActivityPeriod::create([
            'collaborator_id' => $collaborator->id,
            'started_at' => $collaborator->hire_date,
            'changed_by' => $administrator->id,
        ]);
        EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'created_by' => $administrator,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($administrator)
            ->put(route('collaborators.update', $collaborator), [
                'full_name' => 'Nombre actualizado',
                'identity_document' => $collaborator->identity_document,
                'biometric_id' => $collaborator->biometric_id,
                'phone' => '',
                'email' => '',
                'address' => '',
                'job_role_id' => $collaborator->job_role_id,
                'hire_date' => $collaborator->hire_date->toDateString(),
                'is_active' => '0',
                'notes' => 'Observación editada',
            ])->assertRedirectToRoute('collaborators.show', $collaborator);

        $collaborator->refresh();

        $this->assertSame('Nombre actualizado', $collaborator->full_name);
        $this->assertTrue($collaborator->is_active);
        $this->assertSame('Observación editada', $collaborator->notes);
        $this->assertCount(1, $collaborator->employmentConditions);

        $this->actingAs($administrator)->patch(route('collaborators.toggle-status', $collaborator), [
            'status_note' => 'Ausencia temporal acordada.',
        ])->assertRedirectToRoute('collaborators.index');

        $collaborator->refresh();
        $this->assertFalse($collaborator->is_active);
        $this->assertStringContainsString('Observación editada', $collaborator->notes);
        $this->assertStringContainsString('Ausencia temporal acordada.', $collaborator->notes);
        $this->assertNotNull($collaborator->activityPeriods()->firstOrFail()->ended_at);
    }

    public function test_new_condition_closes_previous_condition_and_preserves_both(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        $previous = EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'created_by' => $administrator,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
        ]);

        $this->actingAs($administrator)
            ->post(route('collaborators.conditions.store', $collaborator), [
                'job_role_id' => $collaborator->job_role_id,
                'monthly_salary' => '3200.50',
                'weekly_hours' => '34',
                'effective_from' => '2026-06-01',
                'effective_to' => '',
                'reason' => 'Nuevo acuerdo',
                'notes' => '',
            ])->assertRedirectToRoute('collaborators.show', $collaborator);

        $this->assertSame('2026-05-31', $previous->fresh()->effective_to->toDateString());
        $this->assertCount(2, $collaborator->employmentConditions);
        $this->assertDatabaseHas('employment_conditions', [
            'collaborator_id' => $collaborator->id,
            'monthly_salary' => '3200.50',
            'created_by' => $administrator->id,
        ]);
    }

    public function test_hire_date_cannot_be_moved_after_existing_condition_history(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'created_by' => $administrator,
            'effective_from' => '2025-02-01',
        ]);

        $this->actingAs($administrator)
            ->put(route('collaborators.update', $collaborator), [
                'full_name' => $collaborator->full_name,
                'identity_document' => $collaborator->identity_document,
                'biometric_id' => $collaborator->biometric_id,
                'phone' => $collaborator->phone,
                'email' => $collaborator->email,
                'address' => $collaborator->address,
                'job_role_id' => $collaborator->job_role_id,
                'hire_date' => '2025-03-01',
                'is_active' => '1',
                'notes' => $collaborator->notes,
            ])->assertSessionHasErrors('hire_date');

        $this->assertSame('2025-01-01', $collaborator->fresh()->hire_date->toDateString());
    }

    public function test_condition_cannot_start_before_latest_condition(): void
    {
        $administrator = User::factory()->create();
        $collaborator = Collaborator::factory()->create(['hire_date' => '2025-01-01']);
        EmploymentCondition::factory()->create([
            'collaborator_id' => $collaborator,
            'created_by' => $administrator,
            'effective_from' => '2026-06-01',
        ]);

        $this->actingAs($administrator)
            ->post(route('collaborators.conditions.store', $collaborator), [
                'job_role_id' => $collaborator->job_role_id,
                'monthly_salary' => '3000',
                'weekly_hours' => '30',
                'effective_from' => '2026-05-01',
                'effective_to' => '',
                'reason' => '',
                'notes' => '',
            ])->assertSessionHasErrors('effective_from');

        $this->assertCount(1, $collaborator->employmentConditions);
    }

    private function validPayload(JobRole $jobRole): array
    {
        return [
            'full_name' => 'María Flores',
            'identity_document' => 'CI-123',
            'biometric_id' => '37',
            'phone' => '70000000',
            'email' => 'maria@example.com',
            'address' => 'La Paz',
            'job_role_id' => $jobRole->id,
            'hire_date' => '2026-01-10',
            'is_active' => '1',
            'notes' => 'Registro de prueba',
            'monthly_salary' => '2500',
            'weekly_hours' => '30',
            'effective_from' => '2026-01-10',
            'effective_to' => '',
            'condition_reason' => 'Condición inicial',
            'condition_notes' => '',
        ];
    }
}
