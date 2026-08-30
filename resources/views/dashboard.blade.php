@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Panel administrativo</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">Hola, {{ auth()->user()->name }}</h1>
            <p class="mt-2 max-w-2xl text-slate-600">La base del sistema está lista. Las funciones de administración se incorporarán progresivamente en las siguientes etapas.</p>
        </div>

        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm" aria-labelledby="foundation-title">
            <div class="flex items-start gap-4">
                <span class="grid size-11 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6" /></svg>
                </span>
                <div>
                    <h2 id="foundation-title" class="text-lg font-semibold">Fundación del sistema configurada</h2>
                    <p class="mt-1 text-sm leading-6 text-slate-600">El acceso privado, la navegación base y la configuración regional ya están disponibles.</p>
                </div>
            </div>
        </section>
    </div>
@endsection
