@extends('layouts.app')
@section('title', 'Revisión de importación')
@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Revisión del origen</p><h1 class="mt-2 text-3xl font-bold">{{ $biometricImport->controlPeriod->name }}</h1><p class="mt-2 text-sm text-slate-600">{{ $biometricImport->original_filename }} · importado {{ $biometricImport->imported_at->locale('es')->translatedFormat('d \d\e F \d\e Y, H:i') }}</p></div>
            <a href="{{ route('biometric-imports.index') }}" class="button-secondary">Volver a importaciones</a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach([['Personas', $biometricImport->people_count], ['Vinculadas', $biometricImport->matched_people_count], ['Sin vincular', $biometricImport->unmatched_people_count], ['Marcaciones', $biometricImport->mark_count], ['Advertencias', $biometricImport->warning_count]] as [$label, $value])
                <div class="card"><p class="text-xs font-semibold uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">{{ $value }}</p></div>
            @endforeach
        </div>

        @if($biometricImport->unmatched_people_count || $biometricImport->warning_count)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
                <h2 class="font-semibold">Importación conservada con observaciones</h2>
                <p class="mt-1">Las personas sin coincidencia y las celdas no vacías sin horas reconocibles se muestran abajo. No se creó ni modificó ningún colaborador.</p>
            </div>
        @endif

        <div class="space-y-4">
            @foreach($biometricImport->people as $person)
                <details class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <summary class="cursor-pointer list-none px-5 py-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold">{{ $person->source_name }}</h2><p class="mt-1 text-xs text-slate-500">ID biométrico: {{ $person->source_biometric_id }} · Departamento de origen: {{ $person->source_department ?? 'No informado' }}</p></div><div class="flex flex-wrap gap-2">@if($person->collaborator)<span class="status-badge bg-emerald-100 text-emerald-800">Vinculado: {{ $person->collaborator->full_name }}</span>@else<span class="status-badge bg-red-100 text-red-800">Sin colaborador vinculado</span>@endif @if($person->name_differs)<span class="status-badge bg-slate-100 text-slate-700">Nombre en biométrico: {{ $person->source_name }}</span>@endif</div></div>
                    </summary>
                    <div class="overflow-x-auto border-t border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr class="text-left text-xs font-semibold uppercase text-slate-500"><th class="px-5 py-3">Fecha</th><th class="px-5 py-3">Valor original</th><th class="px-5 py-3">Marcaciones extraídas</th><th class="px-5 py-3">Observación</th></tr></thead><tbody class="divide-y divide-slate-100">
                            @foreach($person->days as $day)
                                <tr><td class="whitespace-nowrap px-5 py-3 text-sm">{{ $day->mark_date->format('d/m/Y') }}</td><td class="px-5 py-3 text-sm text-slate-600">{{ $day->original_value ?? '—' }}</td><td class="px-5 py-3 text-sm">{{ $day->marks->pluck('source_text')->join(' · ') ?: 'Sin marcaciones' }}</td><td class="px-5 py-3 text-sm">@if($day->extraction_warning)<span class="text-amber-700">Texto sin una hora válida</span>@else—@endif</td></tr>
                            @endforeach
                        </tbody></table>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endsection
