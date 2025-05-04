<?php

namespace App\Supports;

use Illuminate\Support\Carbon;

class TanggalMerah extends ATanggalMerah
{
    public static ATanggalMerah $t;
    public static ?array $tanggalMerahDates = null;
    public static function getLiburDates(int $year = null)
    {
        if (self::$tanggalMerahDates) return self::$tanggalMerahDates;
        self::$t ??= new ATanggalMerah();
        $res = collect(self::$t->getData())->keys()->flatten()->toArray();
        unset($res[count($res) - 1]);
        $sabtu = self::getDatesByDayName(Carbon::parse("2024-01-01"), now()->addYear(1), Carbon::SATURDAY);
        $ahad = self::getDatesByDayName(Carbon::parse("2024-01-01"), now()->addYear(1), Carbon::SUNDAY);
        self::$tanggalMerahDates = collect(array_merge($res, $sabtu, $ahad))
            ->sort()->unique()->flatten()->toArray();
        // if(!collect(self::$tanggalMerahDates)->flatten()->contains("2024-04-21")) dd($sabtu,$ahad);
        return self::$tanggalMerahDates;
    }
    public static function getHariTerakhirTidakLibur()
    {
        $res = now();
        while (self::isLibur($res)) {
            $res->addDay(-1);
        }
        return $res;
    }

    public static function getDatesByDayName(Carbon $fromDate, Carbon $toDate, int $dayId = Carbon::SUNDAY)
    {
        $res = [];
        $startDate = $fromDate->next($dayId); // Get the first friday.
        $endDate = $toDate;
        for ($date = $startDate; $date->lte($endDate); $date->addWeek()) {
            $res[] = $date->format('Y-m-d');
        }
        return $res;
    }

    public static function getNextWorkDay(Carbon $date, int $step = 1)
    {
        $tanggalMerahDates = collect(self::getLiburDates())->flatten();
        $originalDate = Carbon::parse($date);
        $tes = 0;
        while ($tanggalMerahDates->contains($date->toDateString())) {
            $tes += 1;
            if ($tes > 10) dd("While inaf");
            $date->addDay($step);
        }
        return $date;
    }
    public static function isLibur(Carbon $date)
    {
        $tanggalMerahDates = collect(self::getLiburDates())->flatten();
        return $tanggalMerahDates->contains($date->toDateString());
    }
}
