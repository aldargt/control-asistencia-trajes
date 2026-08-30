<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePrimaryAdministrator extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Crea el administrador principal del sistema';

    public function handle(): int
    {
        if (User::query()->where('is_primary_admin', true)->exists()) {
            $this->error('Ya existe un administrador principal. No es posible crear otro.');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Nombre')),
            'email' => mb_strtolower(trim((string) $this->ask('Correo electrónico'))),
            'password' => (string) $this->secret('Contraseña'),
            'password_confirmation' => (string) $this->secret('Confirmación de contraseña'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($data): bool {
            User::query()->lockForUpdate()->get(['id']);

            if (User::query()->where('is_primary_admin', true)->exists()) {
                return false;
            }

            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => User::ROLE_ADMINISTRATOR,
                'is_primary_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return true;
        });

        if (! $created) {
            $this->error('Ya existe un administrador principal. No es posible crear otro.');

            return self::FAILURE;
        }

        $this->info('Administrador principal creado correctamente.');

        return self::SUCCESS;
    }
}
