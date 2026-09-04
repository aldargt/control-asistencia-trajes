@extends('layouts.app')
@section('title', 'Cálculo de horas')
@section('content')
@php
    use App\Support\Duration;
    $labels = ['complete' => 'Definitivo', 'provisional' => 'Provisional', 'configuration_pending' => 'Configuración pendiente'];
    $balanceLabels = ['compliance' => 'Cumplimiento', 'deficit' => 'Déficit', 'surplus' => 'Excedente'];
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div><p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Etapa 8</p><h1 class="mt-2 text-3xl font-bold">Cálculo de horas</h1><p class="mt-2 text-sm text-slate-600">Consulta los resultados persistidos o actualízalos explícitamente después de corregir jornadas.</p></div>
        @if($selectedPeriod)<form method="POST" action="{{ route('attendance-calculations.store', $selectedPeriod) }}">@csrf<button class="button-primary">{{ $calculations->isEmpty() ? 'Calcular período' : 'Recalcular período' }}</button></form>@endif
    </div>
    <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-2 text-base shadow-sm">
        <a href="{{ route('attendance-calculations.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-100">Períodos</a>
        @if($selectedPeriod)<span class="text-slate-400">›</span><a href="{{ route('attendance-calculations.index', ['control_period_id' => $selectedPeriod->id]) }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-100">{{ $selectedPeriod->name }}</a>@endif
        @if($selectedCalculation)<span class="text-slate-400">›</span><span class="px-3 py-2 font-semibold text-slate-500" aria-current="page">{{ $selectedCalculation->collaborator?->full_name ?? $selectedCalculation->person->source_name }}</span>@endif
    </nav>

    @if(!$selectedPeriod)
        <section><h2 class="text-xl font-semibold">Selecciona un período</h2><div class="mt-4 grid gap-4 md:grid-cols-2">@forelse($periods as $period)<a href="{{ route('attendance-calculations.index', ['control_period_id' => $period->id]) }}" class="card block transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md"><div class="flex items-center justify-between"><h3 class="text-xl font-bold">{{ $period->name }}</h3><span class="text-2xl" aria-hidden="true">›</span></div></a>@empty<div class="card md:col-span-2 py-12 text-center text-slate-500">Todavía no hay períodos con jornadas interpretadas.</div>@endforelse</div></section>
    @elseif($calculations->isEmpty())
        <div class="card py-12 text-center"><h2 class="font-semibold">Este período aún no fue calculado</h2><p class="mt-1 text-sm text-slate-500">Usa “Calcular período” para generar resultados desde las jornadas interpretadas.</p></div>
    @elseif(!$selectedCalculation)
        <section>
            <h2 class="text-xl font-semibold">Colaboradores con actividad</h2><p class="mt-1 text-sm text-slate-500">Selecciona un colaborador para abrir su calendario mensual.</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @forelse($activeCalculations as $calculation)
                    <a class="card block transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md" href="{{ route('attendance-calculations.index', ['control_period_id' => $selectedPeriod->id, 'calculation_id' => $calculation->id]) }}"><h3 class="text-lg font-bold">{{ $calculation->collaborator?->full_name ?? $calculation->person->source_name }}</h3><p class="mt-2 text-sm text-slate-600">{{ Duration::human($calculation->recognized_minutes) }} reconocidas</p>@if($calculation->pending_days)<p class="mt-2 text-xs font-semibold text-amber-700"><span aria-hidden="true">●</span> {{ $calculation->pending_days }} pendiente(s)</p>@endif</a>
                @empty<div class="card md:col-span-2 lg:col-span-3 text-center text-slate-500">No hay colaboradores con actividad en este período.</div>@endforelse
            </div>
            @if($withoutMarksCalculations->isNotEmpty())
                <details class="card mt-5"><summary class="cursor-pointer font-semibold text-slate-700">Ver colaboradores sin marcaciones ({{ $withoutMarksCalculations->count() }})</summary><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach($withoutMarksCalculations as $calculation)<a class="rounded-xl border border-slate-200 p-4 transition hover:bg-slate-50" href="{{ route('attendance-calculations.index', ['control_period_id' => $selectedPeriod->id, 'calculation_id' => $calculation->id]) }}"><p class="font-semibold">{{ $calculation->collaborator?->full_name ?? $calculation->person->source_name }}</p><p class="mt-1 text-sm text-slate-500">Sin marcaciones</p></a>@endforeach</div></details>
            @endif
        </section>
    @else
        <section class="space-y-5">
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-2xl font-bold">{{ $selectedCalculation->collaborator?->full_name ?? $selectedCalculation->person->source_name }}</h2><p class="text-sm text-slate-500">Calculado: {{ $selectedCalculation->calculated_at->format('d/m/Y H:i') }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold">{{ $labels[$selectedCalculation->status] }}</span></div>
                <dl class="mt-6 grid gap-4 sm:grid-cols-3"><div><dt class="text-sm text-slate-500">Horas esperadas</dt><dd class="mt-1 text-xl font-bold">{{ Duration::human($selectedCalculation->expected_minutes) }}</dd></div><div><dt class="text-sm text-slate-500">Horas reconocidas</dt><dd class="mt-1 text-xl font-bold">{{ Duration::human($selectedCalculation->recognized_minutes) }}</dd></div><div><dt class="text-sm text-slate-500">{{ $selectedCalculation->balance_status ? $balanceLabels[$selectedCalculation->balance_status] : 'Diferencia' }}</dt><dd class="mt-1 text-xl font-bold {{ ($selectedCalculation->difference_minutes ?? 0) < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ Duration::human($selectedCalculation->difference_minutes, true) }}</dd></div></dl>
                @if($selectedCalculation->status === 'provisional')<p class="mt-5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900"><strong>Diferencia provisional:</strong> existen {{ $selectedCalculation->pending_days }} jornadas pendientes de revisión. La diferencia no constituye un déficit definitivo.</p>@elseif($selectedCalculation->status === 'configuration_pending')<p class="mt-5 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">No se pudieron determinar minutos esperados: revisa la condición laboral aplicable.</p>@endif
                @if($selectedCalculation->no_marks_days)<p class="mt-3 text-sm text-slate-500">{{ $selectedCalculation->no_marks_days }} día(s) sin marcaciones, conservados como ausencia de datos y no como jornadas de cero horas.</p>@endif
            </div>
            <div>
                <h3 class="text-xl font-semibold">{{ $selectedPeriod->name }}</h3><p class="mt-1 text-sm text-slate-500">Selecciona una jornada para consultar los intervalos ya calculados.</p>
                <div class="mt-4 flex flex-wrap gap-4 text-xs font-medium"><span class="text-emerald-700"><span aria-hidden="true">●</span> Compatible</span><span class="text-amber-700"><span aria-hidden="true">●</span> Requiere revisión</span><span class="text-blue-700"><span aria-hidden="true">●</span> Corregida</span><span class="text-slate-500"><span aria-hidden="true">●</span> Sin marcaciones</span></div>
                <div class="card mt-4 overflow-x-auto p-3 sm:p-5"><div class="min-w-[38rem]"><div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 sm:gap-2">@foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<div class="py-2">{{ $weekday }}</div>@endforeach</div><div class="mt-1 space-y-1 sm:space-y-2">
                    @foreach($calendarWeeks as $week)<div class="grid grid-cols-7 gap-1 sm:gap-2">@foreach($week as $cell)
                        @if(!$cell)<div class="min-h-24 rounded-xl bg-slate-50 sm:min-h-28"></div>
                        @else
                            @php($day = $cell['data'])
                            @php($state = !$day || $day->status === 'no_marks' ? 'no_marks' : ($day->status === 'pending' ? 'pending' : ($day->source_type === 'correction' ? 'corrected' : 'compatible')))
                            @php($stateLabel = ['no_marks' => 'Sin marcaciones', 'pending' => 'Requiere revisión', 'corrected' => 'Corregida', 'compatible' => 'Compatible'][$state])
                            @php($stateClasses = ['no_marks' => 'border-slate-100 bg-slate-50 text-slate-500', 'pending' => 'border-amber-400 bg-amber-50 text-amber-950 hover:bg-amber-100', 'corrected' => 'border-blue-300 bg-blue-50 text-blue-900 hover:bg-blue-100', 'compatible' => 'border-emerald-300 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'][$state])
                            @if($state === 'pending')
                                <a href="{{ route('attendance-corrections.index', ['control_period_id' => $selectedPeriod->id, 'person_id' => $selectedCalculation->biometric_import_person_id, 'interpretation_id' => $day->attendance_interpretation_id]) }}" class="min-h-24 rounded-xl border p-2 transition sm:min-h-28 {{ $stateClasses }}"><span class="block text-2xl font-bold leading-none">{{ $cell['day'] }}</span><span class="mt-3 block text-xs font-semibold"><span aria-hidden="true">●</span> {{ $stateLabel }}</span></a>
                            @elseif(in_array($state, ['corrected', 'compatible'], true))
                                <button type="button" data-calculation-day-open data-source="calculation-day-{{ $day->id }}" class="min-h-24 rounded-xl border p-2 text-left transition sm:min-h-28 {{ $stateClasses }}"><span class="block text-2xl font-bold leading-none">{{ $cell['day'] }}</span><span class="mt-2 block text-xs font-semibold"><span aria-hidden="true">●</span> {{ $stateLabel }}</span><span class="mt-2 block text-sm font-bold">{{ Duration::human($day->recognized_minutes) }}</span></button>
                                <template id="calculation-day-{{ $day->id }}"><div><p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Jornada reconocida</p><h2 class="mt-1 text-xl font-bold">{{ $day->work_date->locale('es')->translatedFormat('j \d\e F \d\e Y') }}</h2><div class="mt-5 space-y-3">@foreach($day->intervals as $interval)<div class="rounded-xl bg-slate-50 p-3"><p class="font-semibold">{{ $interval->started_at->format('H:i') }} → {{ $interval->ended_at->format('H:i') }}</p><p class="mt-1 text-sm text-slate-600">{{ Duration::human($interval->minutes) }}</p></div>@endforeach</div><div class="mt-5 border-t border-slate-200 pt-4"><p class="text-sm text-slate-500">Total</p><p class="text-2xl font-bold">{{ Duration::human($day->recognized_minutes) }}</p><p class="mt-4 text-sm font-semibold {{ $state === 'corrected' ? 'text-blue-700' : 'text-emerald-700' }}">{{ $stateLabel }}</p></div></div></template>
                            @else<div class="min-h-24 rounded-xl border p-2 sm:min-h-28 {{ $stateClasses }}"><span class="block text-2xl font-bold leading-none">{{ $cell['day'] }}</span><span class="mt-4 block text-center text-xs">{{ $stateLabel }}</span></div>@endif
                        @endif
                    @endforeach</div>@endforeach
                </div></div></div>
            </div>
        </section>
    @endif
</div>
<dialog id="calculation-day-modal" class="fixed inset-0 m-auto max-h-[92vh] w-[min(34rem,calc(100%-1rem))] overflow-y-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60"><div class="p-5 sm:p-7"><div class="flex justify-end"><button type="button" data-modal-close aria-label="Cerrar" class="rounded-lg px-3 py-1 text-2xl text-slate-500 hover:bg-slate-100">×</button></div><div data-calculation-day-content></div></div></dialog>
@endsection
