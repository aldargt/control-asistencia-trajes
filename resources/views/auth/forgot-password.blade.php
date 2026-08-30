@extends('layouts.guest')

@section('title', 'Recuperar contraseña')

@section('content')
    <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8" aria-labelledby="recovery-title">
        <div class="mb-7">
            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver al inicio de sesión</a>
            <h1 id="recovery-title" class="mt-6 text-2xl font-bold tracking-tight">Recuperar contraseña</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">Ingresa tu correo y te enviaremos un enlace para crear una nueva contraseña.</p>
        </div>

        @if (session('status'))
            <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm leading-6 text-emerald-800" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="mb-2 block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none transition focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">Enviar enlace</button>
        </form>
    </section>
@endsection
