<?php

namespace App\Services;

use App\Exceptions\InvalidBiometricFileException;
use App\Models\ControlPeriod;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

class BiometricAttendanceImporter
{
    private const SHEET_NAME = 'Reporte de Asistencia';

    private const TIME_PATTERN = '/(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?/';

    public function parse(string $path, ControlPeriod $period): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);

            if (! in_array(self::SHEET_NAME, $reader->listWorksheetNames($path), true)) {
                throw new InvalidBiometricFileException('El archivo no contiene la hoja "Reporte de Asistencia".');
            }

            $reader->setLoadSheetsOnly([self::SHEET_NAME]);
            $spreadsheet = $reader->load($path);
        } catch (InvalidBiometricFileException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidBiometricFileException('No fue posible leer el archivo Excel. Verifica que sea un archivo .xls o .xlsx válido.', previous: $exception);
        }

        $sheet = $spreadsheet->getSheetByName(self::SHEET_NAME);

        try {
            $this->validatePeriod($sheet, $period);
            $daysInMonth = $period->starts_at->daysInMonth;
            $this->validateDayHeaders($sheet, $daysInMonth);
            $people = $this->parsePeople($sheet, $period, $daysInMonth);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return ['people' => $people];
    }

    public function extractTimes(?string $value): array
    {
        preg_match_all(self::TIME_PATTERN, $value ?? '', $matches);

        return $matches[0];
    }

    private function validatePeriod(Worksheet $sheet, ControlPeriod $period): void
    {
        $value = trim((string) $sheet->getCell('C3')->getFormattedValue());

        if (! preg_match('/^(\d{4}-\d{2}-\d{2})\s*~\s*(\d{4}-\d{2}-\d{2})$/', $value, $matches)) {
            throw new InvalidBiometricFileException('No se encontró un período válido en la celda C3.');
        }

        $start = Carbon::parse($matches[1]);
        $end = Carbon::parse($matches[2]);

        if (! $start->isSameDay($period->starts_at) || ! $end->isSameDay($period->ends_at)) {
            throw new InvalidBiometricFileException('El período declarado en el Excel no corresponde al período de control seleccionado.');
        }
    }

    private function validateDayHeaders(Worksheet $sheet, int $daysInMonth): void
    {
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $value = $sheet->getCell([$day, 4])->getValue();

            if ((int) $value !== $day || ! is_numeric($value)) {
                $coordinate = Coordinate::stringFromColumnIndex($day).'4';
                throw new InvalidBiometricFileException("La fila de días es inválida en la celda $coordinate.");
            }
        }

        if (trim((string) $sheet->getCell([$daysInMonth + 1, 4])->getFormattedValue()) !== '') {
            throw new InvalidBiometricFileException('La fila de días contiene columnas adicionales que no corresponden al mes seleccionado.');
        }
    }

    private function parsePeople(Worksheet $sheet, ControlPeriod $period, int $daysInMonth): array
    {
        $people = [];
        $seenIds = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($row = 5; $row <= $highestRow; $row += 2) {
            $idLabel = trim((string) $sheet->getCell("A$row")->getFormattedValue());
            $sourceId = trim((string) $sheet->getCell("C$row")->getFormattedValue());
            $nameLabel = trim((string) $sheet->getCell("I$row")->getFormattedValue());
            $sourceName = trim((string) $sheet->getCell("K$row")->getFormattedValue());
            $departmentLabel = trim((string) $sheet->getCell("S$row")->getFormattedValue());
            $sourceDepartment = trim((string) $sheet->getCell("U$row")->getFormattedValue());

            if ($idLabel === '' && $sourceId === '' && $sourceName === '') {
                continue;
            }

            if ($idLabel !== 'ID:' || $nameLabel !== 'Nombre:' || $departmentLabel !== 'Departamento:' || $sourceId === '' || $sourceName === '') {
                throw new InvalidBiometricFileException("La estructura del colaborador es inválida en la fila $row.");
            }

            if (isset($seenIds[$sourceId])) {
                throw new InvalidBiometricFileException("El ID biométrico $sourceId aparece más de una vez en el archivo.");
            }

            $seenIds[$sourceId] = true;
            $days = [];

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $originalValue = (string) $sheet->getCell([$day, $row + 1])->getFormattedValue();
                $times = $this->extractTimes($originalValue);
                $hasOriginalValue = trim($originalValue) !== '';
                $days[] = [
                    'date' => $period->starts_at->copy()->day($day)->toDateString(),
                    'original_value' => $hasOriginalValue ? $originalValue : null,
                    'extraction_warning' => $hasOriginalValue && $times === [],
                    'times' => $times,
                ];
            }

            $people[] = [
                'source_biometric_id' => $sourceId,
                'source_name' => $sourceName,
                'source_department' => $sourceDepartment !== '' ? $sourceDepartment : null,
                'source_row' => $row,
                'days' => $days,
            ];
        }

        if ($people === []) {
            throw new InvalidBiometricFileException('No se encontraron colaboradores con la estructura esperada desde la fila 5.');
        }

        return $people;
    }
}
