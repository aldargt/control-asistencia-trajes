<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-job-roles') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('job_roles')->ignore($this->route('job_role'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'reference_weekly_hours' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'lte:168'],
            'reference_monthly_salary' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
