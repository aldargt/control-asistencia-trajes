@extends('layouts.app')
@section('title', 'Editar período de control')
@section('content')
    <div class="mx-auto max-w-4xl"><div class="mb-6"><a href="{{ route('control-periods.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a períodos</a><h1 class="mt-3 text-3xl font-bold">Editar período de control</h1><p class="mt-2 text-sm text-slate-600">Modificar los días cambia la referencia de horas, nunca el salario acordado.</p></div><form method="POST" action="{{ route('control-periods.update', $controlPeriod) }}" class="card">@csrf @method('PUT') @include('control-periods._form')</form></div>
@endsection
