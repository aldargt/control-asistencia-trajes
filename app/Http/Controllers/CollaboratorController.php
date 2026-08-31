<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCollaboratorRequest;
use App\Http\Requests\UpdateCollaboratorRequest;
use App\Models\Collaborator;
use App\Models\JobRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CollaboratorController extends Controller
{
    public function index(): View
    {
        $collaborators = Collaborator::query()
            ->with(['activityPeriods', 'employmentConditions.jobRole'])
            ->when(request('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('identity_document', 'like', "%{$search}%")
                        ->orWhere('biometric_id', 'like', "%{$search}%");
                });
            })
            ->when(request('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when(request('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('full_name')
            ->paginate(12)
            ->withQueryString();

        return view('collaborators.index', [
            'collaborators' => $collaborators,
            'jobRoles' => JobRole::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('collaborators.create', [
            'jobRoles' => JobRole::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreCollaboratorRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $collaborator = DB::transaction(function () use ($data, $request): Collaborator {
            $collaborator = Collaborator::create(collect($data)->only([
                'job_role_id', 'full_name', 'identity_document', 'biometric_id', 'occupation_status', 'phone',
                'email', 'address', 'hire_date', 'is_active', 'notes',
            ])->all());

            $collaborator->employmentConditions()->create([
                'job_role_id' => $data['job_role_id'],
                'monthly_salary' => $data['monthly_salary'],
                'weekly_hours' => $data['weekly_hours'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'reason' => $data['condition_reason'] ?? 'Condición inicial',
                'notes' => $data['condition_notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $collaborator->activityPeriods()->create([
                'started_at' => $data['hire_date'],
                'changed_by' => $request->user()->id,
            ]);

            return $collaborator;
        });

        return redirect()->route('collaborators.index')
            ->with('success', 'Colaborador creado correctamente.');
    }

    public function show(Collaborator $collaborator): View
    {
        $collaborator->load(['activityPeriods', 'employmentConditions.creator', 'employmentConditions.jobRole']);

        return view('collaborators.show', [
            'collaborator' => $collaborator,
            'jobRoles' => JobRole::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Collaborator $collaborator): View
    {
        return view('collaborators.edit', [
            'collaborator' => $collaborator,
        ]);
    }

    public function update(UpdateCollaboratorRequest $request, Collaborator $collaborator): RedirectResponse
    {
        DB::transaction(function () use ($request, $collaborator): void {
            $data = $request->validated();
            $oldHireDate = $collaborator->hire_date->toDateString();

            $collaborator->update($data);

            if ($oldHireDate !== $collaborator->hire_date->toDateString()) {
                $firstPeriod = $collaborator->activityPeriods()->orderBy('started_at')->first();

                if ($firstPeriod && $firstPeriod->started_at->toDateString() === $oldHireDate) {
                    $firstPeriod->update(['started_at' => $collaborator->hire_date]);
                }
            }

        });

        return redirect()->route('collaborators.show', $collaborator)
            ->with('success', 'Colaborador actualizado correctamente.');
    }

    public function toggleStatus(Request $request, Collaborator $collaborator): RedirectResponse
    {
        $request->validate(['status_note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($request, $collaborator): void {
            $collaborator = Collaborator::query()->lockForUpdate()->findOrFail($collaborator->id);
            $wasActive = $collaborator->is_active;
            $changes = ['is_active' => ! $wasActive];

            if ($wasActive && filled($request->input('status_note'))) {
                $entry = '['.today()->format('d/m/Y').' - Desactivación] '.trim($request->string('status_note'));
                $changes['notes'] = filled($collaborator->notes) ? $collaborator->notes.PHP_EOL.$entry : $entry;
            }

            $collaborator->update($changes);
            $this->synchronizeActivityPeriods($collaborator, $wasActive, $request->user()->id);
        });

        $message = $collaborator->fresh()->is_active
            ? 'Colaborador activado correctamente.'
            : 'Colaborador desactivado correctamente.';

        return redirect()->route('collaborators.index')->with('success', $message);
    }

    private function synchronizeActivityPeriods(Collaborator $collaborator, bool $wasActive, int $userId): void
    {
        if ($wasActive && ! $collaborator->is_active) {
            $period = $collaborator->activityPeriods()->whereNull('ended_at')->latest('started_at')->first();

            if ($period) {
                $period->update([
                    'ended_at' => today()->subDay()->max($period->started_at),
                    'changed_by' => $userId,
                ]);
            }
        }

        if (! $wasActive && $collaborator->is_active) {
            $collaborator->activityPeriods()->create([
                'started_at' => today(),
                'changed_by' => $userId,
            ]);
        }
    }
}
