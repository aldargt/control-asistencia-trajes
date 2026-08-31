<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmploymentConditionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-collaborators') ?? false;
    }

    public function rules(): array
    {
        return [
            'job_role_id' => ['required', Rule::exists('job_roles', 'id')->where('is_active', true)],
            'monthly_salary' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'weekly_hours' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:168'],
            'effective_from' => ['required', 'date', 'after_or_equal:'.$this->route('collaborator')->hire_date->toDateString()],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_from.after_or_equal' => 'El inicio de vigencia no puede ser anterior a la fecha de ingreso.',
            'effective_to.after_or_equal' => 'El fin de vigencia no puede ser anterior a su fecha de inicio.',
        ];
    }
}
