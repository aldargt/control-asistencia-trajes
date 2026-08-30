<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'lowercase', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_ADMINISTRATOR])],
            'is_active' => ['required', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
