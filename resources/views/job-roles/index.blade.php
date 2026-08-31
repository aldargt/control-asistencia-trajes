@extends('layouts.app')
@section('title', 'Roles laborales')
@section('content')
    <div class="mx-auto max-w-5xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Configuración</p><h1 class="mt-2 text-3xl font-bold tracking-tight">Roles laborales</h1><p class="mt-2 text-sm text-slate-600">Catálogo independiente de los permisos de acceso al sistema.</p></div>
            <a href="{{ route('job-roles.create') }}" class="button-primary">Crear rol laboral</a>
        </div>
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if($jobRoles->isEmpty())
                <div class="px-6 py-14 text-center"><h2 class="font-semibold">No hay roles laborales</h2><p class="mt-1 text-sm text-slate-500">Crea el primer rol para registrar colaboradores.</p></div>
            @else
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500"><th class="px-5 py-3.5">Rol laboral</th><th class="px-5 py-3.5">Referencia laboral</th><th class="px-5 py-3.5">Colaboradores</th><th class="px-5 py-3.5">Estado</th><th class="px-5 py-3.5 text-right">Acciones</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($jobRoles as $jobRole)
                            <tr>
                                <td class="px-5 py-4"><p class="font-medium">{{ $jobRole->name }}</p><p class="mt-0.5 max-w-xl text-sm text-slate-500">{{ $jobRole->description ?? 'Sin descripción' }}</p></td>
                                <td class="px-5 py-4 text-sm text-slate-600">@if($jobRole->reference_weekly_hours && $jobRole->reference_monthly_salary)<p>Bs. {{ number_format((float) $jobRole->reference_monthly_salary, 2, ',', '.') }}</p><p class="text-xs text-slate-500">{{ number_format((float) $jobRole->reference_weekly_hours, 2, ',', '.') }} h/semana</p>@else Sin referencia @endif</td>
                                <td class="px-5 py-4 text-sm text-slate-600">{{ $jobRole->collaborators_count }}</td>
                                <td class="px-5 py-4"><span class="status-badge {{ $jobRole->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $jobRole->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="px-5 py-4"><div class="flex flex-wrap justify-end gap-3 text-sm font-semibold"><a href="{{ route('job-roles.edit', $jobRole) }}" class="text-slate-700 hover:text-slate-950">Editar</a><button type="button" data-role-status-open data-action="{{ route('job-roles.toggle-status', $jobRole) }}" data-active="{{ $jobRole->is_active ? '1' : '0' }}" data-role="{{ $jobRole->name }}" class="{{ $jobRole->is_active ? 'text-red-700 hover:text-red-900' : 'text-emerald-700 hover:text-emerald-900' }}">{{ $jobRole->is_active ? 'Desactivar' : 'Activar' }}</button></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
                @if($jobRoles->hasPages())<div class="border-t border-slate-200 px-5 py-4">{{ $jobRoles->links() }}</div>@endif
            @endif
        </div>
        @include('job-roles._status-modal')
    </div>
@endsection
