<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(JobRoleSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('El usuario de prueba solo puede crearse en entornos local o testing.');

            return;
        }

        // Cuenta secundaria exclusiva para pruebas locales. No reemplaza admin:create.
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Usuario de prueba',
                'password' => 'password',
                'role' => User::ROLE_ADMINISTRATOR,
                'is_primary_admin' => false,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
