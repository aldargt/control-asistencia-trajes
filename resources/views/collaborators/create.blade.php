@extends('layouts.app')
@section('title', 'Crear colaborador')
@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('collaborators.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a colaboradores</a>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Crear colaborador</h1>
        <p class="mt-2 text-sm text-slate-600">Registra sus datos y la condición laboral acordada inicialmente.</p>
        <form method="POST" action="{{ route('collaborators.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @include('collaborators._form')
        </form>
    </div>
@endsection
