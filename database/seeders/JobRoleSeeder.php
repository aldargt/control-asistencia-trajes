<?php

namespace Database\Seeders;

use App\Models\JobRole;
use Illuminate\Database\Seeder;

class JobRoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Ayudante', 'Encargado de línea de producción', 'Recepción'] as $name) {
            JobRole::query()->firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
