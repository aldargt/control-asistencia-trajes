@extends('layouts.app')
@section('title', 'Crear rol laboral')
@section('content')
    <div class="mx-auto max-w-2xl"><a href="{{ route('job-roles.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a roles laborales</a><h1 class="mt-3 text-3xl font-bold tracking-tight">Crear rol laboral</h1><p class="mt-2 text-sm text-slate-600">Agrega una función que podrá asignarse a los colaboradores.</p><form method="POST" action="{{ route('job-roles.store') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @include('job-roles._form')</form></div>
@endsection
