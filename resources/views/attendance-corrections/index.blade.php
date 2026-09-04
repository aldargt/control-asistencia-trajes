@extends('layouts.app')
@section('title', 'Inconsistencias y correcciones')
@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wider text-amber-600">Revisión administrativa</p>
            <h1 class="mt-2 text-3xl font-bold">Inconsistencias y correcciones</h1>
            <p class="mt-2 text-sm text-slate-600">Elige un período, un colaborador y el día que deseas revisar. La evidencia biométrica original nunca se modifica.</p>
        </div>

        <nav aria-label="Ruta de navegación" class="flex flex-wrap items-center gap-1 rounded-xl border border-slate-200 bg-white p-2 text-base shadow-sm">
            <a href="{{ route('attendance-corrections.index') }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">Períodos</a>
            @if($selectedPeriod)
                <span class="text-slate-400">›</span>
                <a href="{{ route('attendance-corrections.index', ['control_period_id' => $selectedPeriod->id]) }}" class="rounded-lg px-3 py-2 font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-950">{{ $selectedPeriod->name }}</a>
            @endif
            @if($selectedPerson)
                <span class="text-slate-400">›</span>
                <span class="px-3 py-2 font-semibold text-slate-500" aria-current="page">{{ $selectedPerson->collaborator?->full_name ?? $selectedPerson->source_name }}</span>
            @endif
        </nav>

        @if(!$selectedPeriod)
            <section>
                <h2 class="text-xl font-semibold">Selecciona un período</h2>
                <p class="mt-1 text-sm text-slate-500">Comienza por el mes que deseas revisar.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    @forelse($periods as $period)
                        @php($summary = $periodSummaries[$period->id])
                        <a href="{{ route('attendance-corrections.index', ['control_period_id' => $period->id]) }}" class="card block transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md">
                            <div class="flex items-center justify-between gap-4"><h3 class="text-xl font-bold">{{ $period->name }}</h3><span aria-hidden="true" class="text-2xl">›</span></div>
                            <div class="mt-4 space-y-2 text-sm">
                                @if($summary['review'])<p class="font-semibold text-amber-700"><span aria-hidden="true">●</span> {{ $summary['review'] }} {{ $summary['review'] === 1 ? 'jornada requiere' : 'jornadas requieren' }} revisión</p>@else<p class="font-semibold text-emerald-700"><span aria-hidden="true">●</span> Todo revisado</p>@endif
                                <p class="text-blue-700"><span aria-hidden="true">●</span> {{ $summary['corrected'] }} {{ $summary['corrected'] === 1 ? 'jornada corregida' : 'jornadas corregidas' }}</p>
                                <p class="text-emerald-700"><span aria-hidden="true">●</span> {{ $summary['complete'] }} {{ $summary['complete'] === 1 ? 'jornada compatible' : 'jornadas compatibles' }}</p>
                            </div>
                        </a>
                    @empty
                        <div class="card md:col-span-2 py-12 text-center"><h2 class="font-semibold">Todavía no hay períodos interpretados</h2><p class="mt-1 text-sm text-slate-500">Los períodos aparecerán aquí después de interpretar sus marcaciones.</p></div>
                    @endforelse
                </div>
            </section>
        @elseif(!$selectedPerson)
            <section>
                <h2 class="text-xl font-semibold">{{ $selectedPeriod->name }}</h2>
                <p class="mt-1 text-sm text-slate-500">Selecciona un colaborador para ver su calendario.</p>
                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach($people as $person)
                        @php($summary = $personSummaries[$person->id])
                        <a href="{{ route('attendance-corrections.index', ['control_period_id' => $selectedPeriod->id, 'person_id' => $person->id]) }}" class="card block transition hover:-translate-y-0.5 hover:border-slate-400 hover:shadow-md">
                            <h3 class="text-lg font-bold">{{ $person->collaborator?->full_name ?? $person->source_name }}</h3>
                            <div class="mt-3 space-y-1.5 text-sm">
                                <p class="{{ $summary['review'] ? 'font-semibold text-amber-700' : 'text-slate-500' }}"><span aria-hidden="true">●</span> {{ $summary['review'] }} por revisar</p>
                                <p class="text-blue-700"><span aria-hidden="true">●</span> {{ $summary['corrected'] }} corregidas</p>
                                <p class="text-emerald-700"><span aria-hidden="true">●</span> {{ $summary['complete'] }} compatibles</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @else
            @php($selectedSummary = $personSummaries[$selectedPerson->id])
            <section class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 class="text-2xl font-bold">{{ $selectedPerson->collaborator?->full_name ?? $selectedPerson->source_name }} — {{ $selectedPeriod->name }}</h2><p class="mt-1 text-sm text-slate-500">Haz clic sobre un día con marcaciones para revisarlo.</p></div>
                    @if($selectedSummary['review'])<p class="rounded-xl bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">{{ $selectedSummary['review'] }} {{ $selectedSummary['review'] === 1 ? 'jornada requiere' : 'jornadas requieren' }} revisión</p>@else<p class="rounded-xl bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800">Todo revisado</p>@endif
                </div>
                <div class="flex flex-wrap gap-4 text-xs font-medium"><span class="text-emerald-700"><span aria-hidden="true">●</span> Compatible</span><span class="text-amber-700"><span aria-hidden="true">●</span> Requiere revisión</span><span class="text-blue-700"><span aria-hidden="true">●</span> Corregida</span></div>

                <div class="card overflow-x-auto p-3 sm:p-5">
                    <div class="min-w-[38rem]">
                        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase tracking-wide text-slate-500 sm:gap-2">@foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)<div class="py-2">{{ $weekday }}</div>@endforeach</div>
                        <div class="mt-1 space-y-1 sm:space-y-2">
                            @foreach($calendarWeeks as $week)
                                <div class="grid grid-cols-7 gap-1 sm:gap-2">
                                    @foreach($week as $cell)
                                        @if(!$cell)
                                            <div class="min-h-24 rounded-xl bg-slate-50 sm:min-h-28"></div>
                                        @elseif(!$cell['data'])
                                            <div class="min-h-24 rounded-xl border border-slate-100 p-2 text-slate-400 sm:min-h-28"><span class="font-semibold">{{ $cell['day'] }}</span><span class="mt-4 block text-center text-xs">Sin marcaciones</span></div>
                                        @else
                                            @php($interpretation = $cell['data'])
                                            @php($correction = $interpretation->getRelation('activeCorrectionRecord'))
                                            @php($state = $correction ? 'corrected' : ($interpretation->status === 'requires_review' ? 'review' : 'complete'))
                                            @php($stateLabel = ['corrected' => 'Corregida', 'review' => 'Requiere revisión', 'complete' => 'Compatible'][$state])
                                            @php($stateClasses = ['corrected' => 'border-blue-300 bg-blue-50 text-blue-900 hover:bg-blue-100', 'review' => 'border-amber-400 bg-amber-50 text-amber-950 ring-2 ring-amber-200 hover:bg-amber-100', 'complete' => 'border-emerald-300 bg-emerald-50 text-emerald-900 hover:bg-emerald-100'][$state])
                                            @php($summaryMarks = $correction?->marks ?? $interpretation->marks)
                                            <button type="button" data-correction-open data-source="correction-data-{{ $interpretation->id }}" class="min-h-24 rounded-xl border p-2 text-left transition sm:min-h-28 {{ $stateClasses }}" aria-label="{{ $cell['day'] }} de {{ $selectedPeriod->name }}: {{ $stateLabel }}">
                                                <span class="block text-2xl font-bold leading-none">{{ $cell['day'] }}</span>
                                                <span class="mt-2 block text-xs font-semibold"><span aria-hidden="true">●</span> {{ $stateLabel }}</span>
                                                <span class="mt-2 block space-y-0.5 text-[11px] leading-tight opacity-80">
                                                    @foreach($summaryMarks->take(4)->chunk(2) as $pair)<span class="block whitespace-nowrap">{{ $pair->map(fn ($mark) => $mark->occurred_at->format('H:i'))->join(' · ') }}</span>@endforeach
                                                    @if($summaryMarks->count() > 4)<span class="block font-semibold">+{{ $summaryMarks->count() - 4 }} marcaciones</span>@endif
                                                </span>
                                            </button>
                                        @endif
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>

            @foreach($interpretations as $interpretation)
                @php($correction = $interpretation->getRelation('activeCorrectionRecord'))
                <?php
                    $isFailedModal = (int) old('correction_interpretation_id') === $interpretation->id && $errors->any();
                    $isRequestedModal = ! $errors->any() && $requestedInterpretationId === $interpretation->id;
                    $manual = $correction?->marks->where('source_type', 'manual')->map(fn ($mark) => ['time' => $mark->occurred_at->format('H:i')])->values()->all() ?? [];
                    if ($isFailedModal) {
                        $manual = collect(old('manual_mark_times', []))->map(fn ($time) => ['time' => $time])->all();
                    }
                    $selectedIds = $isFailedModal ? collect(old('selected_marks', []))->map(fn ($id) => (int) $id) : null;
                    $modalData = [
                        'action' => route('attendance-corrections.store', $interpretation),
                        'undoAction' => route('attendance-corrections.destroy', $interpretation),
                        'corrected' => (bool) $correction,
                        'id' => $interpretation->id,
                        'person' => $interpretation->person->collaborator?->full_name ?? $interpretation->person->source_name,
                        'date' => $interpretation->work_date->locale('es')->translatedFormat('j \d\e F \d\e Y'),
                        'nextDate' => $interpretation->work_date->copy()->addDay()->locale('es')->translatedFormat('j \d\e F'),
                        'workDate' => $interpretation->work_date->toDateString(),
                        'nextWorkDate' => $interpretation->work_date->copy()->addDay()->toDateString(),
                        'status' => $correction ? 'Corregida' : ($interpretation->status === 'requires_review' ? 'Requiere revisión' : 'Compatible'),
                        'marks' => $interpretation->marks->map(fn ($mark) => ['id' => $mark->id, 'label' => $mark->occurred_at->format('H:i'), 'occurredAt' => $mark->occurred_at->format('Y-m-d\TH:i:s'), 'selected' => $selectedIds ? $selectedIds->contains($mark->id) : (! $correction || $correction->marks->where('interpreted_mark_id', $mark->id)->isNotEmpty())])->values(),
                        'manual' => $manual,
                        'notes' => $isFailedModal ? old('notes') : $correction?->notes,
                        'errors' => $isFailedModal ? $errors->all() : [],
                        'open' => $isFailedModal || $isRequestedModal,
                    ];
                ?>
                <script type="application/json" id="correction-data-{{ $interpretation->id }}">{!! json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
            @endforeach
        @endif
    </div>

    <dialog id="attendance-correction-modal" class="fixed inset-0 m-auto max-h-[92vh] w-[min(38rem,calc(100%-1rem))] overflow-y-auto rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-950/60">
        <div class="p-5 sm:p-7">
            <div class="flex items-start justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wide text-amber-600">Revisar y corregir</p><h2 class="mt-1 text-xl font-bold">Jornada del <span data-correction-date></span></h2><p class="mt-1 text-sm text-slate-500"><span data-correction-person></span> · <span data-correction-status></span></p></div><button type="button" data-modal-close aria-label="Cerrar" class="rounded-lg px-3 py-1 text-2xl text-slate-500 hover:bg-slate-100">×</button></div>
            <form method="POST" data-correction-form class="mt-6 space-y-5">@csrf<input type="hidden" name="correction_interpretation_id" data-correction-id>
                <div data-correction-errors class="hidden rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert"></div>
                <fieldset><legend class="font-semibold">Marcaciones biométricas</legend><p class="mt-1 text-sm text-slate-500">Selecciona las que realmente corresponden a esta jornada.</p><div data-correction-marks class="mt-3 space-y-2"></div></fieldset>
                <div><div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><p class="font-semibold">Marcaciones faltantes</p><p class="text-sm text-slate-500">Introduce solamente la hora.</p></div><button type="button" data-add-manual-mark class="button-secondary">Agregar marcación faltante</button></div><p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"><strong>Regla de madrugada:</strong> de 00:00 a 05:59 se considera continuación de la jornada anterior. Desde las 06:00 corresponde al nuevo día.</p><div data-manual-marks class="mt-3 space-y-3"></div></div>
                <div><label for="correction_notes" class="mb-2 block text-sm font-semibold">Observación (opcional)</label><input id="correction_notes" name="notes" type="text" maxlength="2000" class="field" placeholder="Ej.: Verificado mediante cámara."></div>
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-sm font-semibold">Vista previa de pares</p><div data-correction-preview class="mt-2 space-y-1 text-sm text-slate-600">Selecciona una cantidad par de marcaciones.</div></div>
                <div data-undo-container class="hidden border-t border-slate-200 pt-4"><button type="button" data-undo-correction class="button-secondary text-red-700">Deshacer corrección</button></div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" data-modal-close class="button-secondary">Cancelar</button><button type="submit" class="button-primary">Guardar corrección</button></div>
            </form>
            <form method="POST" data-undo-form class="hidden">@csrf @method('DELETE')</form>
        </div>
    </dialog>
@endsection
