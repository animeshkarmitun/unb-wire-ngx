<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DisplayPrefs
{
    public static function format(\DateTimeInterface|string|null $dt, ?string $timezone = null, ?string $format = null): string
    {
        if (! $dt) {
            return '';
        }

        $carbon = $dt instanceof CarbonInterface ? $dt : Carbon::parse($dt);
        $tz = $timezone ?? (auth()->user()->timezone ?? 'Asia/Dhaka');
        $fmt = $format ?? (auth()->user()->date_format ?? 'dmy');

        $patterns = [
            'dmy' => 'j M, h:i A',
            'mdy' => 'M j, h:i A',
            'iso' => 'Y-m-d H:i',
        ];

        return $carbon->timezone($tz)->format($patterns[$fmt] ?? $patterns['dmy']);
    }
}
