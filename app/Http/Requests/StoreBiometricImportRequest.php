<?php

namespace App\Http\Requests;

use App\Models\BiometricImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBiometricImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('import-biometric-data') ?? false;
    }

    public function rules(): array
    {
        return [
            'control_period_id' => ['required', 'exists:control_periods,id'],
            'import_file' => ['required', 'file', 'mimes:xls,xlsx', 'max:20480'],
            'confirm_replace' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('control_period_id')) {
                return;
            }

            $alreadyImported = BiometricImport::where('control_period_id', $this->integer('control_period_id'))->exists();

            if ($alreadyImported && ! $this->boolean('confirm_replace')) {
                $validator->errors()->add('confirm_replace', 'Este período ya fue importado. Confirma explícitamente si deseas reemplazar sus datos biométricos.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'import_file.mimes' => 'El archivo debe tener formato .xls o .xlsx.',
            'import_file.max' => 'El archivo no debe superar los 20 MB.',
        ];
    }
}
