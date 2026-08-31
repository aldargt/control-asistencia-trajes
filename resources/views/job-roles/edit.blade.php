@extends('layouts.app')
@section('title', 'Editar rol laboral')
@section('content')
    <div class="mx-auto max-w-2xl"><a href="{{ route('job-roles.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-900">← Volver a roles laborales</a><h1 class="mt-3 text-3xl font-bold tracking-tight">Editar rol laboral</h1><p class="mt-2 text-sm text-slate-600">Actualiza el nombre, descripción o disponibilidad del rol.</p><form method="POST" action="{{ route('job-roles.update', $jobRole) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">@csrf @method('PUT') @include('job-roles._form')</form></div>
@endsection
