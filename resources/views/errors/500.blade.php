@extends('layouts.guest')

@section('title', 'Error del sistema')

@section('content')
    <section class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Error del sistema</p>
        <h1 class="mt-3 text-2xl font-bold">No pudimos completar la operación</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Inténtalo nuevamente. Si el problema continúa, comunícate con el responsable del sistema.</p>
        <a href="{{ url('/') }}" class="mt-7 inline-flex rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Ir al inicio</a>
    </section>
@endsection
