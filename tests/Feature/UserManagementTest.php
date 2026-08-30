<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_user_management(): void
    {
        $this->get(route('users.index'))->assertRedirectToRoute('login');
    }

    public function test_user_without_permission_cannot_access_user_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertForbidden()
            ->assertSee('No tienes permiso para acceder');
    }

    public function test_primary_administrator_can_view_user_management(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();

        $this->actingAs($administrator)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertSee($administrator->email);
    }

    public function test_primary_administrator_can_create_an_active_secondary_administrator(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Maribel Administradora',
            'email' => 'maribel@example.com',
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => '1',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
        ])->assertRedirectToRoute('users.index')
            ->assertSessionHas('success', 'Administrador secundario creado correctamente.');

        $user = User::whereEmail('maribel@example.com')->firstOrFail();

        $this->assertTrue($user->is_active);
        $this->assertTrue($user->isAdministrator());
        $this->assertFalse($user->isPrimaryAdministrator());
        $this->assertTrue(Hash::check('clave-segura', $user->password));
        $this->assertNotSame('clave-segura', $user->password);
    }

    public function test_primary_administrator_can_edit_and_deactivate_another_user(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $this->actingAs($administrator)->put(route('users.update', $user), [
            'name' => 'Nombre actualizado',
            'email' => 'actualizado@example.com',
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirectToRoute('users.index')
            ->assertSessionHas('success', 'Usuario actualizado correctamente.');

        $user->refresh();

        $this->assertSame('Nombre actualizado', $user->name);
        $this->assertFalse($user->is_active);
        $this->assertSame($originalPassword, $user->password);
    }

    public function test_primary_administrator_can_assign_a_new_password(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();
        $user = User::factory()->create();

        $this->actingAs($administrator)->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => '1',
            'password' => 'nueva-clave',
            'password_confirmation' => 'nueva-clave',
        ])->assertRedirectToRoute('users.index');

        $this->assertTrue(Hash::check('nueva-clave', $user->fresh()->password));
    }

    public function test_primary_administrator_cannot_deactivate_own_account_or_change_its_level(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();

        $this->actingAs($administrator)->put(route('users.update', $administrator), [
            'name' => $administrator->name,
            'email' => $administrator->email,
            'role' => User::ROLE_ADMINISTRATOR,
            'is_primary_admin' => '0',
            'is_active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionHasErrors([
            'is_active' => 'No puedes desactivar tu propia cuenta.',
        ]);

        $this->assertTrue($administrator->fresh()->is_active);
        $this->assertTrue($administrator->fresh()->isPrimaryAdministrator());
    }

    public function test_main_user_validations_are_applied(): void
    {
        $administrator = User::factory()->primaryAdministrator()->create();
        $existingUser = User::factory()->create();

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => '',
            'email' => $existingUser->email,
            'role' => 'worker',
            'is_active' => '1',
            'password' => 'corta',
            'password_confirmation' => 'diferente',
        ])->assertSessionHasErrors(['name', 'email', 'role', 'password']);
    }

    public function test_secondary_administrator_cannot_create_another_administrator(): void
    {
        $secondary = User::factory()->create();

        $this->actingAs($secondary)->post(route('users.store'), [
            'name' => 'Otro administrador',
            'email' => 'otro@example.com',
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => '1',
            'password' => 'clave-segura',
            'password_confirmation' => 'clave-segura',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'otro@example.com']);
    }

    public function test_secondary_administrator_cannot_modify_the_primary_administrator(): void
    {
        $primary = User::factory()->primaryAdministrator()->create();
        $secondary = User::factory()->create();

        $this->actingAs($secondary)->put(route('users.update', $primary), [
            'name' => 'Nombre sustituido',
            'email' => $primary->email,
            'role' => User::ROLE_ADMINISTRATOR,
            'is_active' => '0',
            'password' => '',
            'password_confirmation' => '',
        ])->assertForbidden();

        $primary->refresh();

        $this->assertNotSame('Nombre sustituido', $primary->name);
        $this->assertTrue($primary->is_active);
        $this->assertTrue($primary->isPrimaryAdministrator());
    }
}
