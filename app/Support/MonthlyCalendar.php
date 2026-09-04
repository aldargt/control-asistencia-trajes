<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class MonthlyCalendar
{
    public static function weeks(int $year, int $month, array $days = []): array
    {
        $firstDay = Carbon::create($year, $month, 1)->startOfDay();
        $cells = array_fill(0, $firstDay->dayOfWeekIso - 1, null);

        for ($day = 1; $day <= $firstDay->daysInMonth; $day++) {
            $date = $firstDay->copy()->day($day);
            $dateKey = $date->toDateString();
            $cells[] = ['day' => $day, 'date' => $dateKey, 'data' => $days[$dateKey] ?? null];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        return array_chunk($cells, 7);
    }
}
