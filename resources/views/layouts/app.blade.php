<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Panel') · {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[17rem_1fr]">
            <aside id="main-navigation" class="fixed inset-y-0 left-0 z-40 hidden w-72 flex-col bg-slate-950 text-white lg:flex lg:w-[17rem]">
                <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                    <span class="grid size-10 place-items-center rounded-xl bg-amber-400 font-bold text-slate-950">CA</span>
                    <div>
                        <p class="font-semibold leading-tight">Control de asistencia</p>
                        <p class="text-xs text-slate-400">Panel administrativo</p>
                    </div>
                </div>
                <nav class="flex-1 space-y-1 p-4" aria-label="Navegación principal">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('dashboard') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                        <span class="size-2 rounded-full bg-amber-400"></span>
                        Inicio
                    </a>
                    @can('manage-collaborators')
                        <a href="{{ route('collaborators.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('collaborators.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('collaborators.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M22 11h-6" /></svg>
                            Colaboradores
                        </a>
                    @endcan
                    @can('manage-job-roles')
                        <a href="{{ route('job-roles.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('job-roles.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('job-roles.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" /></svg>
                            Roles laborales
                        </a>
                    @endcan
                    @can('manage-control-periods')
                        <a href="{{ route('control-periods.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('control-periods.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('control-periods.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M8 2v4M16 2v4M3 9h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z" /></svg>
                            Períodos de control
                        </a>
                    @endcan
                    @can('import-biometric-data')
                        <a href="{{ route('biometric-imports.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('biometric-imports.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('biometric-imports.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 20h16" /></svg>
                            Importar biométrico
                        </a>
                    @endcan
                    @can('manage-attendance-corrections')
                        <a href="{{ route('attendance-corrections.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('attendance-corrections.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('attendance-corrections.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 9v4M12 17h.01M10.3 3.7 2.6 17a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z" /></svg>
                            Inconsistencias
                        </a>
                    @endcan
                    @can('calculate-attendance')
                        <a href="{{ route('attendance-calculations.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('attendance-calculations.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('attendance-calculations.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h3M14 13h2M8 16h3M14 16h2" /></svg>
                            Cálculo de horas
                        </a>
                    @endcan
                    @can('manage-users')
                        <a href="{{ route('users.index') }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition {{ request()->routeIs('users.*') ? 'bg-white/10 text-white' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif>
                            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" /></svg>
                            Usuarios
                        </a>
                    @endcan
                </nav>
                <div class="border-t border-white/10 p-4">
                    <p class="truncate px-2 text-sm font-medium">{{ auth()->user()->name }}</p>
                    <p class="truncate px-2 pb-3 text-xs text-slate-400">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full rounded-xl border border-white/15 px-4 py-2.5 text-left text-sm font-medium transition hover:bg-white/10" type="submit">Cerrar sesión</button>
                    </form>
                </div>
            </aside>

            <div class="lg:col-start-2">
                <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8">
                    <button id="navigation-toggle" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" type="button" aria-controls="main-navigation" aria-expanded="false">
                        <span class="sr-only">Abrir navegación</span>
                        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <p class="text-sm font-medium text-slate-500">Administración</p>
                    <div class="grid size-9 place-items-center rounded-full bg-slate-900 text-sm font-semibold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                </header>

                <main class="p-4 sm:p-6 lg:p-8">
                    @if (session('success'))
                        <div class="mx-auto mb-6 max-w-6xl rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
                            {{ session('success') }}
                        </div>
                    @endif
                    @yield('content')
                </main>
            </div>
        </div>
        <div id="navigation-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden" aria-hidden="true"></div>
    </body>
</html>
