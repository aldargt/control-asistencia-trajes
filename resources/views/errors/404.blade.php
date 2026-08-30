@extends('layouts.guest')

@section('title', 'Página no encontrada')

@section('content')
    <section class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Error 404</p>
        <h1 class="mt-3 text-2xl font-bold">Página no encontrada</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">La dirección ingresada no existe o ya no está disponible.</p>
        <a href="{{ url('/') }}" class="mt-7 inline-flex rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Ir al inicio</a>
    </section>
@endsection
