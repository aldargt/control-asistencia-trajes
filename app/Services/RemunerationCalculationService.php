<?php

namespace App\Services;

use App\Models\AttendanceCalculation;
use App\Models\AttendanceCalculationDay;
use App\Models\ControlPeriod;
use App\Models\RemunerationCalculation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RemunerationCalculationService
{
    public function calculate(ControlPeriod $period): Collection
    {
        $period->load(['biometricImport.people.collaborator.employmentConditions']);
        $attendanceCalculations = AttendanceCalculation::query()->where('control_period_id', $period->id)
            ->with(['collaborator.employmentConditions', 'days'])->get();

        return DB::transaction(function () use ($period, $attendanceCalculations): Collection {
            $results = collect();
            foreach ($attendanceCalculations as $attendance) {
                $hasActivity = $attendance->days->contains(fn ($day) => $day->status !== AttendanceCalculationDay::STATUS_NO_MARKS);
                if (! $hasActivity || ! $attendance->collaborator) {
                    RemunerationCalculation::where('attendance_calculation_id', $attendance->id)->delete();

                    continue;
                }

                if ($attendance->status !== AttendanceCalculation::STATUS_COMPLETE) {
                    $results->push($this->persistBlocked($attendance, RemunerationCalculation::STATUS_BLOCKED));

                    continue;
                }

                $reference = $period->hourReferenceFor($attendance->collaborator);
                $condition = $reference['condition'] ?? null;
                if (($reference['status'] ?? null) !== 'calculated' || ! $condition || $period->reference_days < 1) {
                    $results->push($this->persistBlocked($attendance, RemunerationCalculation::STATUS_CONFIGURATION_PENDING));

                    continue;
                }

                $salaryCents = $this->decimalToInteger((string) $condition->monthly_salary, 2);
                $weeklyHundredths = $this->decimalToInteger((string) $condition->weekly_hours, 2);
                if ($salaryCents < 0 || $weeklyHundredths < 1) {
                    $results->push($this->persistBlocked($attendance, RemunerationCalculation::STATUS_CONFIGURATION_PENDING));

                    continue;
                }
                $expectedNumerator = $weeklyHundredths * $period->reference_days;
                if ($expectedNumerator % 10 !== 0 || $attendance->expected_minutes !== intdiv($expectedNumerator, 10)) {
                    $results->push($this->persistBlocked($attendance, RemunerationCalculation::STATUS_CONFIGURATION_PENDING));

                    continue;
                }

                $difference = (int) $attendance->difference_minutes;
                $deficit = max(0, -$difference);
                $surplus = max(0, $difference);
                $valuedHundredths = $this->businessDurationHundredths(max($deficit, $surplus));
                $rateNumerator = $salaryCents * 600;
                $rateDenominator = $period->reference_days * $weeklyHundredths;
                $adjustmentCents = $this->roundProductFraction($rateNumerator, $valuedHundredths, $rateDenominator * 100);
                $deduction = $deficit > 0 ? $adjustmentCents : 0;
                $increment = $surplus > 0 ? $adjustmentCents : 0;
                $final = max(0, $salaryCents - $deduction + $increment);

                $results->push(RemunerationCalculation::updateOrCreate(
                    ['attendance_calculation_id' => $attendance->id],
                    ['control_period_id' => $period->id, 'biometric_import_person_id' => $attendance->biometric_import_person_id, 'collaborator_id' => $attendance->collaborator_id, 'employment_condition_id' => $condition->id, 'status' => RemunerationCalculation::STATUS_CALCULATED,
                        'monthly_salary_cents' => $salaryCents, 'weekly_hours_hundredths' => $weeklyHundredths, 'reference_days' => $period->reference_days,
                        'daily_reference_hours' => $this->divideToDecimal($weeklyHundredths, 600, 6), 'expected_minutes' => $attendance->expected_minutes,
                        'recognized_minutes' => $attendance->recognized_minutes, 'difference_minutes' => $difference, 'deficit_minutes' => $deficit, 'surplus_minutes' => $surplus,
                        'valued_duration_hundredths' => $valuedHundredths, 'hourly_rate' => $this->divideToDecimal($rateNumerator, $rateDenominator * 100, 10),
                        'base_amount_cents' => $salaryCents, 'deficit_deduction_cents' => $deduction, 'surplus_increment_cents' => $increment,
                        'final_amount_cents' => $final, 'source_attendance_calculated_at' => $attendance->calculated_at,
                        'source_condition_updated_at' => $condition->updated_at, 'calculated_at' => now()],
                ));
            }

            return $results;
        });
    }

    public function businessDurationHundredths(int $minutes): int
    {
        return intdiv($minutes, 60) * 100 + $minutes % 60;
    }

    public function isStale(RemunerationCalculation $remuneration, ControlPeriod $period): bool
    {
        if ($remuneration->status !== RemunerationCalculation::STATUS_CALCULATED) {
            return false;
        }
        $remuneration->loadMissing('attendanceCalculation', 'collaborator.employmentConditions');
        $attendance = $remuneration->attendanceCalculation;
        $reference = $period->hourReferenceFor($remuneration->collaborator);
        $condition = $reference['condition'] ?? null;

        return ! $attendance || ! $condition
            || $condition->id !== $remuneration->employment_condition_id
            || $this->decimalToInteger((string) $condition->monthly_salary, 2) !== $remuneration->monthly_salary_cents
            || $this->decimalToInteger((string) $condition->weekly_hours, 2) !== $remuneration->weekly_hours_hundredths
            || $period->reference_days !== $remuneration->reference_days
            || $attendance->expected_minutes !== $remuneration->expected_minutes
            || $attendance->recognized_minutes !== $remuneration->recognized_minutes
            || ! $attendance->calculated_at->equalTo($remuneration->source_attendance_calculated_at);
    }

    private function persistBlocked(AttendanceCalculation $attendance, string $status): RemunerationCalculation
    {
        return RemunerationCalculation::updateOrCreate(['attendance_calculation_id' => $attendance->id], [
            'control_period_id' => $attendance->control_period_id, 'biometric_import_person_id' => $attendance->biometric_import_person_id,
            'collaborator_id' => $attendance->collaborator_id, 'employment_condition_id' => null, 'status' => $status,
            'monthly_salary_cents' => null, 'weekly_hours_hundredths' => null, 'reference_days' => null, 'daily_reference_hours' => null,
            'expected_minutes' => $attendance->expected_minutes, 'recognized_minutes' => $attendance->recognized_minutes,
            'difference_minutes' => $attendance->difference_minutes, 'deficit_minutes' => 0, 'surplus_minutes' => 0,
            'valued_duration_hundredths' => 0, 'hourly_rate' => null, 'base_amount_cents' => null,
            'deficit_deduction_cents' => 0, 'surplus_increment_cents' => 0, 'final_amount_cents' => null,
            'source_attendance_calculated_at' => $attendance->calculated_at, 'source_condition_updated_at' => null, 'calculated_at' => null,
        ]);
    }

    private function decimalToInteger(string $value, int $scale): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) ($whole.str_pad(substr($fraction, 0, $scale), $scale, '0'));
    }

    private function roundProductFraction(int $left, int $right, int $denominator): int
    {
        $common = $this->greatestCommonDivisor($left, $denominator);
        $left = intdiv($left, $common);
        $denominator = intdiv($denominator, $common);
        $common = $this->greatestCommonDivisor($right, $denominator);
        $right = intdiv($right, $common);
        $denominator = intdiv($denominator, $common);

        if ($left <= intdiv(PHP_INT_MAX, max(1, $right))) {
            $numerator = $left * $right;
            $quotient = intdiv($numerator, $denominator);
            $remainder = $numerator % $denominator;

            return $quotient + ($remainder >= intdiv($denominator, 2) + $denominator % 2 ? 1 : 0);
        }

        $numerator = bcmul((string) $left, (string) $right, 0);
        $quotient = bcdiv($numerator, (string) $denominator, 0);
        $remainder = bcmod($numerator, (string) $denominator);
        if (bccomp(bcmul($remainder, '2', 0), (string) $denominator, 0) >= 0) {
            $quotient = bcadd($quotient, '1', 0);
        }
        if (bccomp($quotient, (string) PHP_INT_MAX, 0) > 0) {
            throw new \OverflowException('El resultado monetario excede la capacidad de almacenamiento del sistema.');
        }

        return (int) $quotient;
    }

    private function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return abs($left);
    }

    private function divideToDecimal(int $numerator, int $denominator, int $scale): string
    {
        $whole = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;
        $fraction = '';
        for ($index = 0; $index < $scale; $index++) {
            $remainder *= 10;
            $fraction .= intdiv($remainder, $denominator);
            $remainder %= $denominator;
        }

        return $whole.'.'.$fraction;
    }
}
