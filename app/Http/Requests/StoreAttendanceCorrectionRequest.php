<?php

namespace App\Http\Requests;

use App\Models\AttendanceInterpretation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreAttendanceCorrectionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('manual_mark_times')) {
            return;
        }

        /** @var AttendanceInterpretation $interpretation */
        $interpretation = $this->route('attendance_interpretation');
        $dayTypes = $this->input('manual_mark_days', []);
        $manualMarks = collect($this->input('manual_mark_times', []))->map(function ($time, $index) use ($dayTypes, $interpretation): ?string {
            if (! $time) {
                return null;
            }

            $date = $interpretation->work_date->copy();
            $isNextMorning = array_key_exists($index, $dayTypes)
                ? $dayTypes[$index] === 'next_morning'
                : $time < '06:00';
            if ($isNextMorning) {
                $date->addDay();
            }

            return $date->format('Y-m-d').'T'.$time;
        })->all();

        $this->merge(['manual_marks' => $manualMarks]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-attendance-corrections') ?? false;
    }

    public function rules(): array
    {
        return [
            'selected_marks' => ['nullable', 'array'],
            'selected_marks.*' => ['integer', 'distinct', 'exists:interpreted_marks,id'],
            'manual_marks' => ['nullable', 'array'],
            'manual_marks.*' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2})?$/'],
            'manual_mark_times' => ['nullable', 'array'],
            'manual_mark_times.*' => ['nullable', 'date_format:H:i'],
            'manual_mark_days' => ['nullable', 'array'],
            'manual_mark_days.*' => ['nullable', 'in:same_day,next_morning'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'correction_interpretation_id' => ['nullable', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var AttendanceInterpretation $interpretation */
            $interpretation = $this->route('attendance_interpretation');
            $selectedIds = collect($this->input('selected_marks', []))->map(fn ($id): int => (int) $id);
            $validIds = $interpretation->marks()->whereIn('id', $selectedIds)->pluck('id');

            if ($validIds->count() !== $selectedIds->count()) {
                $validator->errors()->add('selected_marks', 'Una de las marcaciones seleccionadas no pertenece a esta jornada.');
            }

            $manualMarks = collect($this->input('manual_marks', []))->filter();
            $timestamps = $interpretation->marks()->whereIn('id', $selectedIds)->pluck('occurred_at')
                ->map(fn ($value): string => Carbon::parse($value)->format('Y-m-d H:i:s'));

            foreach ($manualMarks as $index => $value) {
                $occurredAt = Carbon::parse($value);
                $dayType = $this->input("manual_mark_days.$index");
                $errorKey = $this->has('manual_mark_times') ? "manual_mark_times.$index" : "manual_marks.$index";

                if ($dayType === 'next_morning' && $occurredAt->format('H:i:s') >= '06:00:00') {
                    $validator->errors()->add($errorKey, 'La marcación no corresponde a esta jornada. Las horas desde las 06:00 pertenecen al siguiente día. Para registrar una continuación de madrugada, utiliza una hora entre 00:00 y 05:59.');
                }

                $workDate = $occurredAt->format('H:i:s') < '06:00:00'
                    ? $occurredAt->copy()->subDay()->toDateString()
                    : $occurredAt->toDateString();

                if ($workDate !== $interpretation->work_date->toDateString()) {
                    $validator->errors()->add($errorKey, 'La marcación no corresponde a esta jornada. Selecciona el día correcto para esa hora.');
                }

                $timestamps->push($occurredAt->format('Y-m-d H:i:s'));
            }

            if ($timestamps->count() < 2 || $timestamps->count() % 2 !== 0) {
                $validator->errors()->add('selected_marks', 'Selecciona o agrega una cantidad par de marcaciones, con un mínimo de dos.');
            }

            if ($timestamps->unique()->count() !== $timestamps->count()) {
                $validator->errors()->add('selected_marks', 'No se permiten marcaciones duplicadas en la corrección.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'manual_marks.*.regex' => 'La fecha y hora de la marcación manual no tienen un formato válido.',
            'manual_mark_times.*.date_format' => 'Introduce la hora con el formato HH:MM.',
            'notes.max' => 'La observación no debe superar los 2000 caracteres.',
        ];
    }
}
