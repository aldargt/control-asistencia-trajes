@extends('layouts.app')
@section('title', 'Colaboradores')
@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Personal</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Colaboradores</h1>
                <p class="mt-2 text-sm text-slate-600">Administra datos, roles e historial de condiciones laborales.</p>
            </div>
            <a href="{{ route('collaborators.create') }}" class="button-primary">Crear colaborador</a>
        </div>

        <form method="GET" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_12rem_auto]">
            <label class="sr-only" for="search">Buscar colaboradores</label>
            <input id="search" name="search" type="search" value="{{ request('search') }}" placeholder="Buscar por nombre, documento o ID biométrico" class="field">
            <label class="sr-only" for="status">Filtrar por estado</label>
            <select id="status" name="status" class="field">
                <option value="">Todos los estados</option>
                <option value="active" @selected(request('status') === 'active')>Activos</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactivos</option>
            </select>
            <button class="button-secondary" type="submit">Filtrar</button>
        </form>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($collaborators->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h2 class="font-semibold">No se encontraron colaboradores</h2>
                    <p class="mt-1 text-sm text-slate-500">Registra un colaborador o modifica los filtros.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th class="px-5 py-3.5">Colaborador</th><th class="px-5 py-3.5">Rol laboral</th><th class="px-5 py-3.5">Condición vigente</th><th class="px-5 py-3.5">Antigüedad</th><th class="px-5 py-3.5">Estado</th><th class="px-5 py-3.5 text-right">Acciones</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($collaborators as $collaborator)
                                @php($condition = $collaborator->currentEmploymentCondition())
                                <tr>
                                    <td class="px-5 py-4"><p class="font-medium">{{ $collaborator->full_name }}</p><p class="mt-0.5 text-xs text-slate-500">ID biométrico: {{ $collaborator->biometric_id ?? 'Sin asignar' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $condition?->jobRole?->name ?? 'Sin rol vigente' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        @if($condition)<p>Bs. {{ number_format((float) $condition->monthly_salary, 2, ',', '.') }}</p><p class="text-xs text-slate-500">{{ number_format((float) $condition->weekly_hours, 2, ',', '.') }} h/semana</p>@else<span class="text-amber-700">Sin condición vigente</span>@endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $collaborator->seniority }}</td>
                                    <td class="px-5 py-4"><span class="status-badge {{ $collaborator->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $collaborator->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                                    <td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-x-3 gap-y-2 text-sm font-semibold">
                                        <a href="{{ route('collaborators.show', $collaborator) }}" class="text-slate-700 hover:text-slate-950">Ver detalle</a>
                                        <button type="button" data-condition-open data-collaborator="{{ $collaborator->id }}" data-action="{{ route('collaborators.conditions.store', $collaborator) }}" data-person="de {{ $collaborator->full_name }}" class="text-amber-700 hover:text-amber-900">Nueva condición</button>
                                        <a href="{{ route('collaborators.edit', $collaborator) }}" class="text-slate-700 hover:text-slate-950">Editar</a>
                                        <button type="button" data-collaborator-status-open data-action="{{ route('collaborators.toggle-status', $collaborator) }}" data-active="{{ $collaborator->is_active ? '1' : '0' }}" data-person="{{ $collaborator->full_name }}" class="{{ $collaborator->is_active ? 'text-red-700 hover:text-red-900' : 'text-emerald-700 hover:text-emerald-900' }}">{{ $collaborator->is_active ? 'Desactivar' : 'Activar' }}</button>
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($collaborators->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $collaborators->links() }}</div>@endif
            @endif
        </div>
        @include('collaborators._condition-modal', ['conditionAction' => null, 'currentCondition' => null])
        @include('collaborators._status-modal')
    </div>
@endsection
