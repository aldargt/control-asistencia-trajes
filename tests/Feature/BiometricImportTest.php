<?php

namespace Tests\Feature;

use App\Exceptions\InvalidBiometricFileException;
use App\Models\BiometricImport;
use App\Models\BiometricImportDay;
use App\Models\BiometricMark;
use App\Models\Collaborator;
use App\Models\ControlPeriod;
use App\Models\User;
use App\Services\BiometricAttendanceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

class BiometricImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrators_can_access_biometric_imports(): void
    {
        $this->get(route('biometric-imports.index'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['role' => 'viewer']))
            ->get(route('biometric-imports.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())
            ->get(route('biometric-imports.index'))->assertOk()->assertSee('Importar biométrico');
    }

    public function test_import_preserves_source_data_links_known_people_and_extracts_marks(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2024, 2);
        $collaborator = Collaborator::factory()->create(['biometric_id' => '15', 'full_name' => 'Ana Pérez']);
        $file = $this->report($period, [
            ['id' => '15', 'name' => 'Ana Perez', 'department' => 'Producción', 'days' => [1 => '08:01 12:00:59 13:0018:05']],
            ['id' => '999', 'name' => 'Sin Registro', 'department' => 'Recepción', 'days' => [1 => '', 2 => 'SIN MARCA']],
        ]);

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $file,
        ])->assertRedirect()->assertSessionHas('success', 'Archivo biométrico importado correctamente.');

        $import = BiometricImport::firstOrFail();
        $this->assertSame(2, $import->people_count);
        $this->assertSame(1, $import->matched_people_count);
        $this->assertSame(1, $import->unmatched_people_count);
        $this->assertSame(4, $import->mark_count);
        $this->assertSame(1, $import->warning_count);
        $this->assertSame($collaborator->id, $import->people()->where('source_biometric_id', '15')->value('collaborator_id'));
        $this->assertNull($import->people()->where('source_biometric_id', '999')->value('collaborator_id'));
        $this->assertSame(1, Collaborator::count());
        $this->assertDatabaseHas('biometric_import_days', ['original_value' => '08:01 12:00:59 13:0018:05']);
        $this->assertSame(['08:01', '12:00:59', '13:00', '18:05'], BiometricMark::orderBy('sequence')->pluck('source_text')->all());
        $this->assertSame(58, BiometricImportDay::count());
        $this->assertTrue($import->people()->where('source_biometric_id', '15')->value('name_differs'));
        Storage::disk('local')->assertExists($import->stored_path);
    }

    public function test_all_known_mark_formats_are_extracted_globally_in_source_order(): void
    {
        $importer = app(BiometricAttendanceImporter::class);

        $this->assertSame(['08:38'], $importer->extractTimes('08:38'));
        $this->assertSame(['08:38', '20:30'], $importer->extractTimes('08:38 20:30'));
        $this->assertSame(['08:38', '20:30'], $importer->extractTimes('08:3820:30'));
        $this->assertSame(['00:22', '09:21', '19:31', '23:15'], $importer->extractTimes('00:2209:2119:3123:15'));
        $this->assertSame(['23:33', '23:33'], $importer->extractTimes('23:3323:33'));
        $this->assertSame(['08:50', '13:59', '15:04', '19:33'], $importer->extractTimes('08:5013:5915:0419:33'));
        $this->assertSame(['12:01', '17:05', '17:10', '20:07', '23:10'], $importer->extractTimes('12:0117:0517:1020:0723:10'));
        $this->assertSame(['08:38:12', '20:30:59'], $importer->extractTimes('08:38:12 20:30:59'));
    }

    public function test_inactive_collaborator_with_marks_is_imported_without_changing_status(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 2);
        $collaborator = Collaborator::factory()->create(['biometric_id' => '15', 'is_active' => false]);

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $this->report($period, [['id' => '15', 'name' => $collaborator->full_name, 'days' => [1 => '08:00']]]),
        ])->assertSessionHasNoErrors();

        $import = BiometricImport::firstOrFail();
        $this->assertSame($collaborator->id, $import->people()->firstOrFail()->collaborator_id);
        $this->assertSame(1, $import->mark_count);
        $this->assertFalse($collaborator->fresh()->is_active);
        $this->get(route('biometric-imports.show', $import))
            ->assertOk()
            ->assertSee('Colaborador inactivo con actividad biométrica durante el período');
    }

    public function test_review_starts_with_people_collapsed_and_presents_source_name_as_context(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 2);
        Collaborator::factory()->create(['biometric_id' => '6', 'full_name' => 'Joseph']);

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $this->report($period, [['id' => '6', 'name' => 'Oli', 'days' => [1 => '08:00']]]),
        ])->assertSessionHasNoErrors();

        $response = $this->get(route('biometric-imports.show', BiometricImport::firstOrFail()));

        $response->assertOk()
            ->assertSee('Vinculado: Joseph')
            ->assertSee('Nombre en biométrico: Oli')
            ->assertDontSee('El nombre no coincide')
            ->assertDontSee('shadow-sm" open', false);
    }

    public function test_parser_supports_months_of_28_29_30_and_31_days_and_empty_marks(): void
    {
        $administrator = User::factory()->create();
        $importer = app(BiometricAttendanceImporter::class);

        foreach ([[2023, 2, 28], [2024, 2, 29], [2026, 4, 30], [2026, 1, 31]] as [$year, $month, $days]) {
            $period = $this->period($administrator, $year, $month);
            $path = $this->reportPath($period, [['id' => '1', 'name' => 'Persona', 'days' => []]]);
            $parsed = $importer->parse($path, $period);
            $this->assertCount($days, $parsed['people'][0]['days']);
            $this->assertNull($parsed['people'][0]['days'][$days - 1]['original_value']);
            unlink($path);
        }
    }

    public function test_parser_rejects_missing_sheet_wrong_period_and_invalid_day_structure(): void
    {
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 4);
        $importer = app(BiometricAttendanceImporter::class);

        $missingSheet = $this->reportPath($period, [], sheetName: 'Otra hoja');
        try {
            $importer->parse($missingSheet, $period);
            $this->fail('Se esperaba rechazo por hoja ausente.');
        } catch (InvalidBiometricFileException $exception) {
            $this->assertStringContainsString('Reporte de Asistencia', $exception->getMessage());
        } finally {
            unlink($missingSheet);
        }

        $wrongPeriod = $this->period($administrator, 2026, 5);
        $path = $this->reportPath($wrongPeriod, [['id' => '1', 'name' => 'Persona', 'days' => []]]);
        $this->expectException(InvalidBiometricFileException::class);
        try {
            $importer->parse($path, $period);
        } finally {
            unlink($path);
        }
    }

    public function test_invalid_day_header_is_rejected(): void
    {
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 4);
        $path = $this->reportPath($period, [['id' => '1', 'name' => 'Persona', 'days' => []]], invalidDay: 12);

        $this->expectException(InvalidBiometricFileException::class);
        try {
            app(BiometricAttendanceImporter::class)->parse($path, $period);
        } finally {
            unlink($path);
        }
    }

    public function test_missing_period_information_is_rejected(): void
    {
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 4);
        $path = $this->reportPath($period, [['id' => '1', 'name' => 'Persona', 'days' => []]], periodValue: '');

        $this->expectException(InvalidBiometricFileException::class);
        try {
            app(BiometricAttendanceImporter::class)->parse($path, $period);
        } finally {
            unlink($path);
        }
    }

    public function test_reimport_requires_confirmation_and_replaces_only_previous_period_import(): void
    {
        Storage::fake('local');
        $administrator = User::factory()->create();
        $period = $this->period($administrator, 2026, 1);

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $this->report($period, [['id' => '1', 'name' => 'Primera', 'days' => [1 => '08:00']]]),
        ])->assertSessionHasNoErrors();
        $first = BiometricImport::firstOrFail();
        $oldPath = $first->stored_path;

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $this->report($period, [['id' => '2', 'name' => 'Segunda', 'days' => [1 => '09:00']]]),
        ])->assertSessionHasErrors('confirm_replace');
        $this->assertDatabaseHas('biometric_imports', ['id' => $first->id]);

        $this->actingAs($administrator)->post(route('biometric-imports.store'), [
            'control_period_id' => $period->id,
            'import_file' => $this->report($period, [['id' => '2', 'name' => 'Segunda', 'days' => [1 => '09:00']]]),
            'confirm_replace' => '1',
        ])->assertSessionHas('success', 'Importación biométrica reemplazada correctamente.');

        $this->assertSame(1, BiometricImport::count());
        $this->assertDatabaseMissing('biometric_import_people', ['source_biometric_id' => '1']);
        $this->assertDatabaseHas('biometric_import_people', ['source_biometric_id' => '2']);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists(BiometricImport::firstOrFail()->stored_path);
    }

    private function period(User $user, int $year, int $month): ControlPeriod
    {
        return ControlPeriod::create(['year' => $year, 'month' => $month, 'reference_days' => 26, 'created_by' => $user->id]);
    }

    private function report(ControlPeriod $period, array $people): UploadedFile
    {
        return new UploadedFile($this->reportPath($period, $people), 'reporte-asistencia.xls', 'application/vnd.ms-excel', null, true);
    }

    private function reportPath(ControlPeriod $period, array $people, string $sheetName = 'Reporte de Asistencia', ?int $invalidDay = null, ?string $periodValue = null): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetName);
        $sheet->setCellValue('C3', $periodValue ?? $period->starts_at->toDateString().' ~ '.$period->ends_at->toDateString());

        for ($day = 1; $day <= $period->starts_at->daysInMonth; $day++) {
            $sheet->setCellValue([$day, 4], $day === $invalidDay ? 99 : $day);
        }

        foreach ($people as $index => $person) {
            $row = 5 + ($index * 2);
            $sheet->setCellValue("A$row", 'ID:');
            $sheet->setCellValue("C$row", $person['id']);
            $sheet->setCellValue("I$row", 'Nombre:');
            $sheet->setCellValue("K$row", $person['name']);
            $sheet->setCellValue("S$row", 'Departamento:');
            $sheet->setCellValue("U$row", $person['department'] ?? '');
            foreach ($person['days'] as $day => $value) {
                $sheet->setCellValue([$day, $row + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'biometric-').'.xls';
        (new Xls($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
