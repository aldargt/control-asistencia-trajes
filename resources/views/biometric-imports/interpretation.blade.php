@extends('layouts.app')
@section('title', 'Interpretación de marcaciones')
@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Motor de interpretación</p><h1 class="mt-2 text-3xl font-bold">{{ $biometricImport->controlPeriod->name }}</h1><p class="mt-2 text-sm text-slate-600">Agrupación derivada de marcaciones. Los datos originales permanecen intactos.</p></div>
            <div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('biometric-imports.interpretation.store', $biometricImport) }}">@csrf<button type="submit" class="button-primary">Reprocesar</button></form><a href="{{ route('biometric-imports.show', $biometricImport) }}" class="button-secondary">Ver datos originales</a></div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="card"><p class="text-xs font-semibold uppercase text-slate-500">Jornadas representadas</p><p class="mt-2 text-2xl font-bold">{{ $interpretationCount }}</p></div>
            <div class="card"><p class="text-xs font-semibold uppercase text-slate-500">Requieren revisión</p><p class="mt-2 text-2xl font-bold">{{ $reviewCount }}</p></div>
            <div class="card"><p class="text-xs font-semibold uppercase text-slate-500">Duplicados consolidados</p><p class="mt-2 text-2xl font-bold">{{ $duplicateCount }}</p></div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
            Las marcaciones entre 00:00 y 05:59:59 se asignan a la jornada anterior. Desde las 06:00 permanecen en su fecha calendario. Las secuencias impares sólo se identifican para revisión; aquí no se calculan horas ni remuneraciones.
        </div>

        <div class="space-y-4">
            @forelse($peopleWithMarks as $person)
                <details data-interpretation-person class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <summary class="cursor-pointer list-none px-5 py-4"><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold">{{ $person->collaborator?->full_name ?? $person->source_name }}</h2><p class="mt-1 text-xs text-slate-500">ID biométrico {{ $person->source_biometric_id }} · {{ $person->attendanceInterpretations->where('status', 'requires_review')->count() }} jornadas requieren revisión</p>@if($person->collaborator && ! $person->collaborator->is_active)<p class="mt-2 text-sm font-medium text-amber-700">Colaborador inactivo con actividad biométrica durante el período.</p>@endif</div><span class="text-sm text-slate-500">Mostrar jornadas</span></div></summary>
                    <div class="overflow-x-auto border-t border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr class="text-left text-xs font-semibold uppercase text-slate-500"><th class="px-5 py-3">Jornada</th><th class="px-5 py-3">Secuencia lógica</th><th class="px-5 py-3">Duplicados</th><th class="px-5 py-3">Resultado</th></tr></thead><tbody class="divide-y divide-slate-100">
                            @foreach($person->attendanceInterpretations as $interpretation)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-3 text-sm">{{ $interpretation->work_date->format('d/m/Y') }}</td>
                                    <td class="px-5 py-3 text-sm">
                                        @forelse($interpretation->marks as $mark)
                                            <span class="mr-2 inline-flex flex-col rounded-lg bg-slate-100 px-2.5 py-1.5 align-top"><span class="font-medium">{{ $mark->occurred_at->format('H:i:s') }}{{ $mark->type ? ' · '.($mark->type === 'entry' ? 'Entrada' : 'Salida') : '' }}</span>@if($mark->assigned_from_early_morning)<span class="text-xs text-slate-500">Origen: {{ $mark->occurred_at->format('d/m/Y') }}</span>@endif @if($mark->source_marks_count > 1)<span class="text-xs text-slate-500">{{ $mark->source_marks_count }} originales consolidadas</span>@endif</span>
                                        @empty<span class="text-slate-400">Sin marcaciones</span>@endforelse
                                    </td>
                                    <td class="px-5 py-3 text-sm">{{ $interpretation->duplicate_marks_count }}</td>
                                    <td class="px-5 py-3 text-sm">@if($interpretation->status === 'complete')<span class="status-badge bg-slate-100 text-slate-700">Secuencia compatible con pares</span>@elseif($interpretation->status === 'requires_review')<span class="status-badge bg-amber-100 text-amber-800">Requiere revisión</span>@else<span class="text-slate-500">Sin marcaciones; sin clasificación laboral</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody></table>
                    </div>
                </details>
            @empty
                <div class="card py-12 text-center"><h2 class="font-semibold">No existen personas con actividad biométrica</h2><p class="mt-1 text-sm text-slate-500">La importación es válida, pero ninguna persona tiene marcaciones disponibles para interpretar.</p></div>
            @endforelse
        </div>

        @if($peopleWithoutMarks->isNotEmpty())
            <details class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <summary class="cursor-pointer px-5 py-4 font-semibold">Ver personas sin marcaciones ({{ $peopleWithoutMarks->count() }})</summary>
                <div class="border-t border-slate-200 px-5 py-4"><p class="mb-4 text-sm text-slate-600">Fueron detectadas y conservadas en la importación, pero el biométrico no registró marcaciones para ellas durante el período.</p><ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($peopleWithoutMarks as $person)<li data-no-marks-person class="rounded-xl bg-slate-50 p-3"><p class="font-medium">{{ $person->collaborator?->full_name ?? $person->source_name }}</p><p class="text-xs text-slate-500">ID biométrico {{ $person->source_biometric_id }}{{ $person->collaborator ? '' : ' · Sin vincular' }}</p></li>@endforeach</ul></div>
            </details>
        @endif
    </div>
@endsection
