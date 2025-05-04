<?php

namespace App\Supports;

use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class Helpers
{
    /**
     * Calculate integer number of weeks between Jan 1, 1970 and a given date
     *
     * @param string $date The date string in format "mm/dd/yyyy"
     * @return int The integer number of weeks
     */
    public static function calculateWeeks($date): int
    {
        // Handle different input types
        // if (is_string($date)) {
        //     // Parse the input date string
        //     $dateObj = Carbon::createFromFormat('m/d/Y', $date);

        //     if (!$dateObj) {
        //         throw new InvalidArgumentException("Invalid date format. Please use mm/dd/yyyy or provide a Carbon instance");
        //     }
        // } elseif ($date instanceof Carbon) {
        //     // Use the Carbon instance directly
        //     $dateObj = $date;
        // } else {
        //     dump("Instance:", $date);
        //     throw new InvalidArgumentException("Date must be a string or Carbon instance");
        // }
        $dateObj = $date;


        // Get epoch date (January 1, 1970)
        $epoch = Carbon::create(1970, 1, 1);

        // Calculate days between dates
<<<<<<< HEAD
        $differenceInDays = -$dateObj->diffInDays($epoch);
=======
        $differenceInDays = $dateObj->diffInDays($epoch);
>>>>>>> 09b007952ed43104eb6a70f2935cbb59e5be456c

        // Calculate weeks and take integer portion
        $weeks = intval($differenceInDays / 7);

        return $weeks;
    }

    /**
     * Mengembalikan array tanggal di antara dua tanggal.
     *
     * @param string $startDate Format: 'Y-m-d'
     * @param string $endDate Format: 'Y-m-d'
     * @param string $format Format output, default 'Y-m-d'
     * @return array
     */
    public static function getTanggalDiAntara(string $startDate, string $endDate, string $format = 'Y-m-d'): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $period = CarbonPeriod::create($start, $end);
        $tanggalArray = [];

        foreach ($period as $date) {
            $tanggalArray[] = $date->format($format);
        }

        return $tanggalArray;
    }
}
