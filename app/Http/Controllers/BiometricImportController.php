<?php

namespace App\Http\Controllers;

use App\Exceptions\InvalidBiometricFileException;
use App\Http\Requests\StoreBiometricImportRequest;
use App\Models\BiometricImport;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Services\BiometricAttendanceImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BiometricImportController extends Controller
{
    public function index(): View
    {
        return view('biometric-imports.index', [
            'periods' => ControlPeriod::query()->with('biometricImport')->orderByDesc('year')->orderByDesc('month')->get(),
        ]);
    }

    public function store(StoreBiometricImportRequest $request, BiometricAttendanceImporter $importer): RedirectResponse
    {
        $period = ControlPeriod::findOrFail($request->integer('control_period_id'));
        $file = $request->file('import_file');

        try {
            $parsed = $importer->parse($file->getRealPath(), $period);
        } catch (InvalidBiometricFileException $exception) {
            throw ValidationException::withMessages(['import_file' => $exception->getMessage()]);
        }

        $extension = mb_strtolower($file->getClientOriginalExtension());
        $storedPath = $file->storeAs(
            'biometric-imports/'.$period->year.'/'.str_pad((string) $period->month, 2, '0', STR_PAD_LEFT),
            Str::uuid().'.'.$extension,
            'local',
        );

        if (! $storedPath) {
            throw ValidationException::withMessages(['import_file' => 'No fue posible conservar el archivo original.']);
        }

        $previousImport = BiometricImport::where('control_period_id', $period->id)->first();

        try {
            $biometricImport = DB::transaction(function () use ($request, $period, $file, $storedPath, $parsed, $previousImport): BiometricImport {
                $previousImport?->delete();
                $collaborators = Collaborator::query()->get()->keyBy(fn (Collaborator $collaborator): string => (string) $collaborator->biometric_id);
                $counts = ['people' => 0, 'matched' => 0, 'unmatched' => 0, 'marks' => 0, 'warnings' => 0];

                $biometricImport = BiometricImport::create([
                    'control_period_id' => $period->id,
                    'imported_by' => $request->user()->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'stored_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'imported_at' => now(),
                ]);

                foreach ($parsed['people'] as $sourcePerson) {
                    $collaborator = $collaborators->get($sourcePerson['source_biometric_id']);
                    $person = $biometricImport->people()->create([
                        'collaborator_id' => $collaborator?->id,
                        'source_biometric_id' => $sourcePerson['source_biometric_id'],
                        'source_name' => $sourcePerson['source_name'],
                        'source_department' => $sourcePerson['source_department'],
                        'source_row' => $sourcePerson['source_row'],
                        'name_differs' => $collaborator && mb_strtolower(trim($collaborator->full_name)) !== mb_strtolower(trim($sourcePerson['source_name'])),
                    ]);
                    $counts['people']++;
                    $counts[$collaborator ? 'matched' : 'unmatched']++;

                    foreach ($sourcePerson['days'] as $sourceDay) {
                        $day = $person->days()->create([
                            'mark_date' => $sourceDay['date'],
                            'original_value' => $sourceDay['original_value'],
                            'extraction_warning' => $sourceDay['extraction_warning'],
                        ]);
                        $counts['warnings'] += $sourceDay['extraction_warning'] ? 1 : 0;

                        foreach ($sourceDay['times'] as $sequence => $time) {
                            $day->marks()->create([
                                'marked_time' => strlen($time) === 5 ? $time.':00' : $time,
                                'sequence' => $sequence + 1,
                                'source_text' => $time,
                            ]);
                            $counts['marks']++;
                        }
                    }
                }

                $biometricImport->update([
                    'people_count' => $counts['people'],
                    'matched_people_count' => $counts['matched'],
                    'unmatched_people_count' => $counts['unmatched'],
                    'mark_count' => $counts['marks'],
                    'warning_count' => $counts['warnings'],
                ]);

                return $biometricImport;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);
            throw $exception;
        }

        if ($previousImport) {
            Storage::disk('local')->delete($previousImport->stored_path);
        }

        return redirect()->route('biometric-imports.show', $biometricImport)
            ->with('success', $previousImport ? 'Importación biométrica reemplazada correctamente.' : 'Archivo biométrico importado correctamente.');
    }

    public function show(BiometricImport $biometricImport): View
    {
        $biometricImport->load(['controlPeriod', 'importer', 'people.collaborator', 'people.days.marks']);

        return view('biometric-imports.show', compact('biometricImport'));
    }
}
