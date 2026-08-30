<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-users') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'lowercase',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['required', Rule::in([User::ROLE_ADMINISTRATOR])],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()->is($this->route('user')) && ! $this->boolean('is_active')) {
                    $validator->errors()->add('is_active', 'No puedes desactivar tu propia cuenta.');
                }
            },
        ];
    }
}
