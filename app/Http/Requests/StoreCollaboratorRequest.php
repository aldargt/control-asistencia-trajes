<?php

namespace App\Http\Requests;

use App\Models\Collaborator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-collaborators') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identity_document' => $this->filled('identity_document') ? trim($this->string('identity_document')) : null,
            'biometric_id' => $this->filled('biometric_id') ? $this->input('biometric_id') : null,
            'phone' => $this->filled('phone') ? trim($this->string('phone')) : null,
            'email' => $this->filled('email') ? mb_strtolower(trim($this->string('email'))) : null,
            'address' => $this->filled('address') ? trim($this->string('address')) : null,
            'notes' => $this->filled('notes') ? trim($this->string('notes')) : null,
            'condition_reason' => $this->filled('condition_reason') ? trim($this->string('condition_reason')) : null,
            'condition_notes' => $this->filled('condition_notes') ? trim($this->string('condition_notes')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'identity_document' => ['nullable', 'string', 'max:100', 'unique:collaborators,identity_document'],
            'biometric_id' => ['required', 'integer', 'min:1', 'unique:collaborators,biometric_id'],
            'occupation_status' => ['nullable', Rule::in([
                Collaborator::OCCUPATION_STUDENT,
                Collaborator::OCCUPATION_FULL_TIME,
                Collaborator::OCCUPATION_PART_TIME,
            ])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'job_role_id' => [
                'required',
                Rule::exists('job_roles', 'id')->where('is_active', true),
            ],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'monthly_salary' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'weekly_hours' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:168'],
            'effective_from' => ['required', 'date', 'after_or_equal:hire_date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'condition_reason' => ['nullable', 'string', 'max:255'],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hire_date.before_or_equal' => 'La fecha de ingreso no puede ser posterior a hoy.',
            'effective_from.after_or_equal' => 'El inicio de vigencia no puede ser anterior a la fecha de ingreso.',
            'effective_to.after_or_equal' => 'El fin de vigencia no puede ser anterior a su fecha de inicio.',
        ];
    }
}
