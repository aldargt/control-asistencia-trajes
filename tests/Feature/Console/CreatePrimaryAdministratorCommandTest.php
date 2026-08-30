<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreatePrimaryAdministratorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_the_primary_administrator(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nombre', 'Óscar Administrador')
            ->expectsQuestion('Correo electrónico', 'OSCAR@example.com')
            ->expectsQuestion('Contraseña', 'clave-principal')
            ->expectsQuestion('Confirmación de contraseña', 'clave-principal')
            ->expectsOutput('Administrador principal creado correctamente.')
            ->assertSuccessful();

        $administrator = User::whereEmail('oscar@example.com')->firstOrFail();

        $this->assertSame('Óscar Administrador', $administrator->name);
        $this->assertTrue($administrator->isPrimaryAdministrator());
        $this->assertTrue($administrator->is_active);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('clave-principal', $administrator->password));
    }

    public function test_command_prevents_a_second_primary_administrator(): void
    {
        User::factory()->primaryAdministrator()->create();

        $this->artisan('admin:create')
            ->expectsOutput('Ya existe un administrador principal. No es posible crear otro.')
            ->assertFailed();

        $this->assertSame(1, User::where('is_primary_admin', true)->count());
    }

    public function test_command_validates_password_confirmation(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Nombre', 'Administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña', 'clave-principal')
            ->expectsQuestion('Confirmación de contraseña', 'otra-clave')
            ->expectsOutput('La confirmación de contraseña no coincide.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
