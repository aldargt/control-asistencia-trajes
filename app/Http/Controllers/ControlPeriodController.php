<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveControlPeriodRequest;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ControlPeriodController extends Controller
{
    public function index(): View
    {
        return view('control-periods.index', [
            'periods' => ControlPeriod::query()->orderByDesc('year')->orderByDesc('month')->paginate(12),
            'collaborators' => Collaborator::query()->with('employmentConditions.jobRole')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('control-periods.create');
    }

    public function store(SaveControlPeriodRequest $request): RedirectResponse
    {
        ControlPeriod::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('control-periods.index')->with('success', 'Período de control creado correctamente.');
    }

    public function edit(ControlPeriod $controlPeriod): View
    {
        return view('control-periods.edit', compact('controlPeriod'));
    }

    public function update(SaveControlPeriodRequest $request, ControlPeriod $controlPeriod): RedirectResponse
    {
        $controlPeriod->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('control-periods.index')->with('success', 'Período de control actualizado correctamente.');
    }
}
