<?php

namespace Tests\Unit;

use App\Support\MonthlyCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MonthlyCalendarTest extends TestCase
{
    #[DataProvider('months')]
    public function test_it_generates_months_with_the_correct_first_weekday_and_length(int $year, int $month, int $weekday, int $days): void
    {
        $weeks = MonthlyCalendar::weeks($year, $month);
        $cells = collect($weeks)->flatten(1);
        $first = $cells->first(fn ($cell) => $cell !== null);
        $last = $cells->filter()->last();

        $this->assertSame($weekday - 1, $cells->search($first));
        $this->assertSame(1, $first['day']);
        $this->assertSame($days, $last['day']);
        $this->assertContains(count($weeks), [4, 5, 6]);
    }

    public static function months(): array
    {
        return [
            'febrero 2026' => [2026, 2, 7, 28],
            'septiembre 2026' => [2026, 9, 2, 30],
            'agosto 2025' => [2025, 8, 5, 31],
            'febrero bisiesto' => [2024, 2, 4, 29],
        ];
    }
}
