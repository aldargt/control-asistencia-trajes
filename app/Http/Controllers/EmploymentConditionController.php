<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmploymentConditionRequest;
use App\Models\Collaborator;
use App\Models\EmploymentCondition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmploymentConditionController extends Controller
{
    public function store(StoreEmploymentConditionRequest $request, Collaborator $collaborator): RedirectResponse
    {
        DB::transaction(function () use ($request, $collaborator): void {
            $collaborator = Collaborator::query()->lockForUpdate()->findOrFail($collaborator->id);
            $latest = $collaborator->employmentConditions()->lockForUpdate()->first();
            $effectiveFrom = Carbon::parse($request->validated('effective_from'));

            if ($latest && $effectiveFrom->lte($latest->effective_from)) {
                throw ValidationException::withMessages([
                    'effective_from' => 'La nueva vigencia debe comenzar después de la condición más reciente.',
                ]);
            }

            if ($latest && ($latest->effective_to === null || $latest->effective_to->gte($effectiveFrom))) {
                $latest->update(['effective_to' => $effectiveFrom->copy()->subDay()]);
            }

            EmploymentCondition::create([
                ...$request->validated(),
                'collaborator_id' => $collaborator->id,
                'created_by' => $request->user()->id,
            ]);

            $effectiveTo = $request->validated('effective_to');

            if ($effectiveFrom->lte(today()) && (! $effectiveTo || Carbon::parse($effectiveTo)->gte(today()))) {
                $collaborator->update(['job_role_id' => $request->validated('job_role_id')]);
            }
        });

        return redirect()->route('collaborators.show', $collaborator)
            ->with('success', 'Nueva condición laboral registrada correctamente.');
    }
}
