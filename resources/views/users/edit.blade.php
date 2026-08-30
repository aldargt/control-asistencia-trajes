@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="mb-6">
            <a href="{{ route('users.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a usuarios</a>
            <h1 class="mt-3 text-3xl font-bold tracking-tight">Editar usuario</h1>
            <p class="mt-2 text-sm text-slate-600">Actualiza los datos, el estado o la contraseña de la cuenta.</p>
        </div>
        <form method="POST" action="{{ route('users.update', $user) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            @method('PUT')
            @include('users._form')
        </form>
    </div>
@endsection
