@extends('layouts.app')
@section('title', 'Importar biométrico')
@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Marcaciones de origen</p>
            <h1 class="mt-2 text-3xl font-bold">Importar biométrico</h1>
            <p class="mt-2 text-sm text-slate-600">Conserva el reporte original y sus marcaciones sin interpretar entradas, salidas ni asistencia.</p>
        </div>

        @if($periods->isEmpty())
            <div class="card py-12 text-center">
                <h2 class="font-semibold">Primero debes crear un período de control</h2>
                <p class="mt-1 text-sm text-slate-500">El período declarado en el Excel debe coincidir exactamente con el período seleccionado.</p>
                <a href="{{ route('control-periods.create') }}" class="button-primary mt-5">Crear período de control</a>
            </div>
        @else
            <form method="POST" action="{{ route('biometric-imports.store') }}" enctype="multipart/form-data" class="card space-y-5">
                @csrf
                <div>
                    <h2 class="card-title">Nueva importación</h2>
                    <p class="mt-1 text-sm text-slate-500">Selecciona el período y el archivo Excel del dispositivo.</p>
                </div>
                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="control_period_id" class="mb-2 block text-sm font-semibold">Período de control</label>
                        <select id="control_period_id" name="control_period_id" class="field @error('control_period_id') field-error @enderror" required>
                            <option value="">Selecciona un período</option>
                            @foreach($periods as $period)
                                <option value="{{ $period->id }}" @selected(old('control_period_id') == $period->id)>
                                    {{ $period->name }}{{ $period->biometricImport ? ' — ya importado' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('control_period_id')<p class="field-message">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="import_file" class="mb-2 block text-sm font-semibold">Archivo biométrico</label>
                        <input id="import_file" name="import_file" type="file" accept=".xls,.xlsx" class="field @error('import_file') field-error @enderror" required>
                        <p class="mt-2 text-xs text-slate-500">Formatos admitidos: .xls y .xlsx. Máximo 20 MB.</p>
                        @error('import_file')<p class="field-message">{{ $message }}</p>@enderror
                    </div>
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <input type="hidden" name="confirm_replace" value="0">
                    <input name="confirm_replace" type="checkbox" value="1" class="mt-0.5 size-4 rounded border-amber-400" @checked(old('confirm_replace'))>
                    <span><strong>Confirmar reemplazo, si el período ya fue importado.</strong><br>Se eliminarán únicamente la importación y el archivo anterior de ese período.</span>
                </label>
                @error('confirm_replace')<p class="field-message">{{ $message }}</p>@enderror
                <button type="submit" class="button-primary">Importar archivo</button>
            </form>
        @endif

        <section class="card">
            <h2 class="card-title">Importaciones por período</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead><tr class="text-left text-xs font-semibold uppercase text-slate-500"><th class="py-3 pr-5">Período</th><th class="px-5 py-3">Archivo</th><th class="px-5 py-3">Personas</th><th class="px-5 py-3">Marcaciones</th><th class="py-3 pl-5 text-right">Acción</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($periods->whereNotNull('biometricImport') as $period)
                            <tr><td class="py-4 pr-5 font-medium">{{ $period->name }}</td><td class="px-5 py-4 text-sm text-slate-600">{{ $period->biometricImport->original_filename }}</td><td class="px-5 py-4 text-sm">{{ $period->biometricImport->people_count }}</td><td class="px-5 py-4 text-sm">{{ $period->biometricImport->mark_count }}</td><td class="py-4 pl-5 text-right"><a class="button-secondary" href="{{ route('biometric-imports.show', $period->biometricImport) }}">Revisar</a></td></tr>
                        @empty
                            <tr><td colspan="5" class="py-10 text-center text-sm text-slate-500">Todavía no existen importaciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
