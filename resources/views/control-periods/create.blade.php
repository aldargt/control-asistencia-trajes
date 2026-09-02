@extends('layouts.app')
@section('title', 'Crear período de control')
@section('content')
    <div class="mx-auto max-w-4xl"><div class="mb-6"><a href="{{ route('control-periods.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a períodos</a><h1 class="mt-3 text-3xl font-bold">Crear período de control</h1><p class="mt-2 text-sm text-slate-600">Define la base mensual utilizada para calcular las horas esperadas.</p></div><form method="POST" action="{{ route('control-periods.store') }}" class="card">@csrf @include('control-periods._form')</form></div>
@endsection
