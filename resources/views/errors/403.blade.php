@extends('layouts.guest')

@section('title', 'Acceso no autorizado')

@section('content')
    <section class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Error 403</p>
        <h1 class="mt-3 text-2xl font-bold">No tienes permiso para acceder</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">Tu cuenta no está autorizada para utilizar esta sección.</p>
        <a href="{{ route('dashboard') }}" class="mt-7 inline-flex rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Volver al panel</a>
    </section>
@endsection
