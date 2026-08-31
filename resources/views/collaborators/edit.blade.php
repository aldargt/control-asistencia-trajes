@extends('layouts.app')
@section('title', 'Editar colaborador')
@section('content')
    <div class="mx-auto max-w-4xl">
        <a href="{{ route('collaborators.show', $collaborator) }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver al detalle</a>
        <h1 class="mt-3 text-3xl font-bold tracking-tight">Editar colaborador</h1>
        <p class="mt-2 text-sm text-slate-600">Actualiza datos personales, contacto, rol laboral o estado.</p>
        <form method="POST" action="{{ route('collaborators.update', $collaborator) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf @method('PUT')
            @include('collaborators._form')
        </form>
    </div>
@endsection
