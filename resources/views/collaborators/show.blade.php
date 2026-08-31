@extends('layouts.app')
@section('title', $collaborator->full_name)
@section('content')
    @php($currentCondition = $collaborator->currentEmploymentCondition())
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><a href="{{ route('collaborators.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a colaboradores</a><h1 class="mt-3 text-3xl font-bold tracking-tight">{{ $collaborator->full_name }}</h1><p class="mt-2 text-sm text-slate-600">Ficha administrativa del colaborador</p></div>
            <div class="flex flex-wrap gap-3"><button type="button" data-condition-open data-collaborator="{{ $collaborator->id }}" data-action="{{ route('collaborators.conditions.store', $collaborator) }}" data-person="de {{ $collaborator->full_name }}" class="button-primary">Nueva condición</button><a href="{{ route('collaborators.edit', $collaborator) }}" class="button-secondary">Editar colaborador</a></div>
        </div>

        <div class="space-y-6">
            <section class="card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Información general</p><h2 class="mt-1 text-2xl font-bold">{{ $collaborator->full_name }}</h2></div><span class="status-badge {{ $collaborator->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $collaborator->is_active ? 'Activo' : 'Inactivo' }}</span></div>
                <dl class="mt-6 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-slate-500">ID biométrico</dt><dd class="mt-1 font-semibold">{{ $collaborator->biometric_id }}</dd></div>
                    <div><dt class="text-slate-500">Fecha de ingreso</dt><dd class="mt-1 font-semibold">{{ $collaborator->hire_date->format('d/m/Y') }}</dd></div>
                    <div><dt class="text-slate-500">Situación / ocupación</dt><dd class="mt-1 font-semibold">{{ $collaborator->occupation_status_label ?? 'Sin especificar' }}</dd></div>
                    <div><dt class="text-slate-500">Antigüedad efectiva</dt><dd class="mt-1 font-semibold">{{ $collaborator->seniority }}</dd></div>
                </dl>
            </section>

            <section class="card">
                <div class="flex items-center justify-between gap-3"><div><p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Acuerdo actual</p><h2 class="mt-1 card-title">Condición laboral vigente</h2></div>@if($currentCondition)<span class="status-badge bg-emerald-100 text-emerald-700">Vigente</span>@endif</div>
                @if($currentCondition)
                    <dl class="mt-6 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <div><dt class="text-slate-500">Rol laboral</dt><dd class="mt-1 font-semibold">{{ $currentCondition->jobRole->name }}</dd></div>
                        <div><dt class="text-slate-500">Salario mensual acordado</dt><dd class="mt-1 font-semibold">Bs. {{ number_format((float) $currentCondition->monthly_salary, 2, ',', '.') }}</dd></div>
                        <div><dt class="text-slate-500">Horas semanales comprometidas</dt><dd class="mt-1 font-semibold">{{ number_format((float) $currentCondition->weekly_hours, 2, ',', '.') }} horas</dd></div>
                        <div><dt class="text-slate-500">Inicio de vigencia</dt><dd class="mt-1 font-semibold">{{ $currentCondition->effective_from->format('d/m/Y') }}</dd></div>
                        <div><dt class="text-slate-500">Fin de vigencia</dt><dd class="mt-1 font-semibold">{{ $currentCondition->effective_to?->format('d/m/Y') ?? 'Sin fecha de fin' }}</dd></div>
                        <div><dt class="text-slate-500">Motivo</dt><dd class="mt-1 font-semibold">{{ $currentCondition->reason ?? 'Sin motivo registrado' }}</dd></div>
                    </dl>
                @else<p class="mt-4 text-sm text-amber-700">No existe una condición vigente para la fecha actual.</p>@endif
            </section>

            <section class="card"><h2 class="card-title">Identificación y contacto</h2><dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-slate-500">Documento de identidad</dt><dd class="mt-1 font-medium">{{ $collaborator->identity_document ?? 'No registrado' }}</dd></div><div><dt class="text-slate-500">Teléfono</dt><dd class="mt-1 font-medium">{{ $collaborator->phone ?? 'No registrado' }}</dd></div><div><dt class="text-slate-500">Correo electrónico</dt><dd class="mt-1 break-all font-medium">{{ $collaborator->email ?? 'No registrado' }}</dd></div><div><dt class="text-slate-500">Dirección</dt><dd class="mt-1 font-medium">{{ $collaborator->address ?? 'No registrada' }}</dd></div></dl></section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="card"><h2 class="card-title">Historial de actividad</h2><p class="mt-1 text-sm text-slate-500">Solo estos períodos acumulan antigüedad.</p><div class="mt-5 space-y-3">@forelse($collaborator->activityPeriods as $period)<div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-4 text-sm"><span><strong>Activo desde</strong> {{ $period->started_at->format('d/m/Y') }}</span><span class="text-slate-500">{{ $period->ended_at ? 'Hasta '.$period->ended_at->format('d/m/Y') : 'En curso' }}</span></div>@empty<p class="text-sm text-slate-500">Sin períodos registrados.</p>@endforelse</div></section>
                <section class="card"><h2 class="card-title">Historial de condiciones</h2><div class="mt-5 space-y-3">@foreach($collaborator->employmentConditions as $condition)@php($conditionStatus = $condition->is($currentCondition) ? 'Vigente' : ($condition->effective_from->isFuture() ? 'Programada' : 'Finalizada'))<article class="rounded-xl border border-slate-200 p-4"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $condition->jobRole->name }}</p><p class="mt-1 text-sm">Bs. {{ number_format((float) $condition->monthly_salary, 2, ',', '.') }} · {{ number_format((float) $condition->weekly_hours, 2, ',', '.') }} h/semana</p></div><span class="status-badge {{ $conditionStatus === 'Vigente' ? 'bg-emerald-100 text-emerald-700' : ($conditionStatus === 'Programada' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-600') }}">{{ $conditionStatus }}</span></div><p class="mt-2 text-xs text-slate-500">{{ $condition->effective_from->format('d/m/Y') }} — {{ $condition->effective_to?->format('d/m/Y') ?? 'Sin fecha de fin' }}</p><p class="mt-2 text-sm text-slate-600">{{ $condition->reason ?? 'Sin motivo registrado' }}</p></article>@endforeach</div></section>
            </div>
            @if($collaborator->notes)<section class="card"><h2 class="card-title">Observaciones</h2><p class="mt-3 whitespace-pre-line text-sm text-slate-600">{{ $collaborator->notes }}</p></section>@endif
        </div>
        @include('collaborators._condition-modal', ['conditionAction' => route('collaborators.conditions.store', $collaborator)])
    </div>
@endsection
