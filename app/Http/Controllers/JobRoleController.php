<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobRoleRequest;
use App\Http\Requests\UpdateJobRoleRequest;
use App\Models\JobRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JobRoleController extends Controller
{
    public function index(): View
    {
        return view('job-roles.index', [
            'jobRoles' => JobRole::query()->withCount('collaborators')->orderBy('name')->paginate(12),
        ]);
    }

    public function create(): View
    {
        return view('job-roles.create');
    }

    public function store(StoreJobRoleRequest $request): RedirectResponse
    {
        JobRole::create($request->validated());

        return redirect()->route('job-roles.index')->with('success', 'Rol laboral creado correctamente.');
    }

    public function edit(JobRole $jobRole): View
    {
        return view('job-roles.edit', compact('jobRole'));
    }

    public function update(UpdateJobRoleRequest $request, JobRole $jobRole): RedirectResponse
    {
        $jobRole->update($request->validated());

        return redirect()->route('job-roles.index')->with('success', 'Rol laboral actualizado correctamente.');
    }

    public function toggleStatus(JobRole $jobRole): RedirectResponse
    {
        $jobRole->update(['is_active' => ! $jobRole->is_active]);

        $message = $jobRole->is_active
            ? 'Rol laboral activado correctamente.'
            : 'Rol laboral desactivado correctamente.';

        return redirect()->route('job-roles.index')->with('success', $message);
    }
}
