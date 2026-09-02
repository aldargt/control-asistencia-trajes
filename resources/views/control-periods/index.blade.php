@extends('layouts.app')
@section('title', 'Períodos de control')
@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Control de horas</p><h1 class="mt-2 text-3xl font-bold">Períodos de control</h1><p class="mt-2 text-sm text-slate-600">Referencia mensual de horas esperadas, sin horarios diarios obligatorios.</p></div><a href="{{ route('control-periods.create') }}" class="button-primary">Crear período</a></div>
        <div class="space-y-6">
            @forelse($periods as $period)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="text-xl font-bold">{{ $period->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $period->reference_days }} días de referencia · base semanal de {{ config('hour-control.weekly_reference_days') }} días</p></div><a href="{{ route('control-periods.edit', $period) }}" class="button-secondary">Editar período</a></div>
                    <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200"><thead class="bg-slate-50"><tr class="text-left text-xs font-semibold uppercase text-slate-500"><th class="px-5 py-3">Colaborador</th><th class="px-5 py-3">Condición laboral</th><th class="px-5 py-3">Horas semanales</th><th class="px-5 py-3">Referencia diaria</th><th class="px-5 py-3">Horas esperadas</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach($collaborators as $collaborator)@php($reference = $period->hourReferenceFor($collaborator))<tr><td class="px-5 py-4"><p class="font-medium">{{ $collaborator->full_name }}</p><p class="text-xs text-slate-500">ID biométrico: {{ $collaborator->biometric_id }}</p></td>@if($reference['status'] === 'calculated')<td class="px-5 py-4 text-sm"><p>{{ $reference['condition']->jobRole?->name ?? 'Rol no disponible' }}</p><p class="text-xs text-slate-500">Salario acordado: Bs. {{ number_format((float) $reference['condition']->monthly_salary, 2, ',', '.') }}</p></td><td class="px-5 py-4 text-sm">{{ number_format($reference['weekly_hours'], 2, ',', '.') }} h</td><td class="px-5 py-4 text-sm">{{ number_format($reference['daily_reference_hours'], 6, ',', '.') }} h</td><td class="px-5 py-4 font-semibold">{{ number_format($reference['expected_hours'], 6, ',', '.') }} h</td>@else<td colspan="4" class="px-5 py-4 text-sm text-amber-700">{{ $reference['status'] === 'multiple_conditions' ? 'Requiere revisión: existen varias condiciones durante el mes.' : 'Sin condición laboral aplicable al período.' }}</td>@endif</tr>@endforeach
                    </tbody></table></div>
                </section>
            @empty<div class="card py-14 text-center"><h2 class="font-semibold">No existen períodos de control</h2><p class="mt-1 text-sm text-slate-500">Crea el primer período para consultar las horas esperadas.</p></div>@endforelse
        </div>
        @if($periods->hasPages())<div class="mt-6">{{ $periods->links() }}</div>@endif
    </div>
@endsection
