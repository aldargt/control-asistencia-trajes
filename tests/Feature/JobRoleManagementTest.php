<?php

namespace Tests\Feature;

use App\Models\Collaborator;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_estimates_salary_proportionally_without_replacing_agreed_salary(): void
    {
        $role = JobRole::factory()->create([
            'reference_weekly_hours' => 48,
            'reference_monthly_salary' => 3000,
        ]);

        $this->assertSame(2125.0, $role->estimateMonthlySalary(34));
    }

    public function test_administrator_can_create_job_role(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs($administrator)->post(route('job-roles.store'), [
            'name' => 'Encargado de línea de producción',
            'description' => 'Coordina la producción.',
            'reference_weekly_hours' => '48',
            'reference_monthly_salary' => '3000',
            'is_active' => '1',
        ])->assertRedirectToRoute('job-roles.index');

        $this->assertDatabaseHas('job_roles', [
            'name' => 'Encargado de línea de producción',
            'is_active' => true,
        ]);
    }

    public function test_job_role_name_must_be_unique(): void
    {
        $administrator = User::factory()->create();
        $role = JobRole::factory()->create(['name' => 'Recepción']);

        $this->actingAs($administrator)->post(route('job-roles.store'), [
            'name' => $role->name,
            'description' => '',
            'reference_weekly_hours' => '48',
            'reference_monthly_salary' => '3000',
            'is_active' => '1',
        ])->assertSessionHasErrors('name');
    }

    public function test_administrator_can_deactivate_role_without_affecting_assigned_collaborators(): void
    {
        $administrator = User::factory()->create();
        $role = JobRole::factory()->create();
        $collaborator = Collaborator::factory()->create(['job_role_id' => $role]);

        $this->actingAs($administrator)->put(route('job-roles.update', $role), [
            'name' => $role->name,
            'description' => 'Actualizado',
            'reference_weekly_hours' => $role->reference_weekly_hours,
            'reference_monthly_salary' => $role->reference_monthly_salary,
            'is_active' => '0',
        ])->assertRedirectToRoute('job-roles.index');

        $this->assertTrue($role->fresh()->is_active);

        $this->actingAs($administrator)
            ->patch(route('job-roles.toggle-status', $role))
            ->assertRedirectToRoute('job-roles.index')
            ->assertSessionHas('success', 'Rol laboral desactivado correctamente.');

        $this->assertFalse($role->fresh()->is_active);
        $this->assertTrue($collaborator->fresh()->jobRole->is($role));
    }

    public function test_non_administrator_cannot_manage_job_roles(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($user)->get(route('job-roles.index'))->assertForbidden();
    }
}
