@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Administración</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Usuarios</h1>
                <p class="mt-2 text-sm text-slate-600">Gestiona las cuentas que pueden acceder al sistema.</p>
            </div>
            <a href="{{ route('users.create') }}" class="rounded-xl bg-slate-950 px-5 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800">Crear usuario</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if ($users->isEmpty())
                <div class="px-6 py-14 text-center">
                    <h2 class="font-semibold">No hay usuarios registrados</h2>
                    <p class="mt-1 text-sm text-slate-500">Crea la primera cuenta administrativa.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <th class="px-5 py-3.5">Usuario</th>
                                <th class="px-5 py-3.5">Rol</th>
                                <th class="px-5 py-3.5">Estado</th>
                                <th class="px-5 py-3.5 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="font-medium text-slate-900">{{ $user->name }}</p>
                                        <p class="mt-0.5 text-sm text-slate-500">{{ $user->email }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        {{ $user->is_primary_admin ? 'Administrador principal' : 'Administrador secundario' }}
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right">
                                        <a href="{{ route('users.edit', $user) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950">Editar</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="border-t border-slate-200 px-5 py-4">{{ $users->links() }}</div>
                @endif
            @endif
        </div>
    </div>
@endsection
