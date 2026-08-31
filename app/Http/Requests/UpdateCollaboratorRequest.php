<?php

namespace App\Http\Requests;

use App\Models\Collaborator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCollaboratorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-collaborators') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['identity_document', 'phone', 'email', 'address', 'notes', 'occupation_status'] as $field) {
            if (! $this->filled($field)) {
                $this->merge([$field => null]);
            }
        }

        if ($this->filled('email')) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')))]);
        }
    }

    public function rules(): array
    {
        $collaborator = $this->route('collaborator');

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'identity_document' => ['nullable', 'string', 'max:100', Rule::unique('collaborators')->ignore($collaborator)],
            'biometric_id' => ['required', 'integer', 'min:1', Rule::unique('collaborators')->ignore($collaborator)],
            'occupation_status' => ['nullable', Rule::in([
                Collaborator::OCCUPATION_STUDENT,
                Collaborator::OCCUPATION_FULL_TIME,
                Collaborator::OCCUPATION_PART_TIME,
            ])],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('hire_date')) {
                    return;
                }

                $firstConditionDate = $this->route('collaborator')->employmentConditions()->min('effective_from');

                if ($firstConditionDate && Carbon::parse($this->input('hire_date'))->gt($firstConditionDate)) {
                    $validator->errors()->add(
                        'hire_date',
                        'La fecha de ingreso no puede ser posterior al inicio del historial laboral.',
                    );
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'hire_date.before_or_equal' => 'La fecha de ingreso no puede ser posterior a hoy.',
        ];
    }
}
