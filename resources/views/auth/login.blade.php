@extends('layouts.guest')

@section('title', 'Iniciar sesión')

@section('content')
    <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl shadow-slate-200/60 sm:p-8" aria-labelledby="login-title">
        <div class="mb-8 text-center">
            <span class="mx-auto mb-4 grid size-12 place-items-center rounded-xl bg-slate-950 font-bold text-amber-400">CA</span>
            <h1 id="login-title" class="text-2xl font-bold tracking-tight">Bienvenido</h1>
            <p class="mt-2 text-sm text-slate-500">Ingresa tus datos para acceder al panel administrativo.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="mb-2 block text-sm font-medium">Correo electrónico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" autofocus required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none transition focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium">Contraseña</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                    class="w-full rounded-xl border border-slate-300 px-3.5 py-3 text-sm outline-none transition focus:border-slate-700 focus:ring-4 focus:ring-slate-100 @error('password') border-red-500 @enderror">
                @error('password')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>

            <label class="flex items-center gap-2.5 text-sm text-slate-600">
                <input name="remember" type="checkbox" class="size-4 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                Mantener la sesión iniciada
            </label>

            <button type="submit" class="w-full rounded-xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">
                Iniciar sesión
            </button>
        </form>
    </section>
@endsection
