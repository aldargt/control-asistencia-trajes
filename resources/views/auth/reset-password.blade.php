@extends('layouts.guest')

@section('title', 'Restablecer contraseña')

@section('content')
    <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8" aria-labelledby="reset-title">
        <h1 id="reset-title" class="text-2xl font-bold tracking-tight">Crear nueva contraseña</h1>
        <p class="mt-2 text-sm text-slate-500">La nueva contraseña debe tener al menos ocho caracteres.</p>

        <form method="POST" action="{{ route('password.store') }}" class="mt-7 space-y-5">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <div>
                <label for="email" class="mb-2 block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" autocomplete="email" required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="mb-2 block text-sm font-medium">Nueva contraseña</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('password') border-red-500 @enderror">
                @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none focus:border-slate-700 focus:ring-4 focus:ring-slate-100">
            </div>
            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">Restablecer contraseña</button>
        </form>
    </section>
@endsection
