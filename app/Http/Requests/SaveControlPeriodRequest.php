<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveControlPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-control-periods') ?? false;
    }

    public function rules(): array
    {
        $period = $this->route('control_period');

        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'month' => [
                'required',
                'integer',
                'between:1,12',
                Rule::unique('control_periods', 'month')
                    ->where(fn ($query) => $query->where('year', $this->integer('year')))
                    ->ignore($period),
            ],
            'reference_days' => ['required', 'integer', 'between:1,31'],
        ];
    }

    public function messages(): array
    {
        return [
            'month.unique' => 'Ya existe un período de control para el mes y año seleccionados.',
            'year.between' => 'El año debe estar entre 2000 y 2100.',
            'month.between' => 'Selecciona un mes válido.',
            'reference_days.between' => 'Los días de referencia deben estar entre 1 y 31.',
        ];
    }
}
