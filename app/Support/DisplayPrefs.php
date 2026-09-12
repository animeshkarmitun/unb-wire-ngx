<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class DisplayPrefs
{
    public static function format(?Carbon $dt, ?string $timezone = null, ?string $format = null): string
    {
        if (! $dt) {
            return '';
        }

        $tz = $timezone ?? (auth()->user()->timezone ?? 'Asia/Dhaka');
        $fmt = $format ?? (auth()->user()->date_format ?? 'dmy');

        $patterns = [
            'dmy' => 'j M, h:i A',
            'mdy' => 'M j, h:i A',
            'iso' => 'Y-m-d H:i',
        ];

        return $dt->timezone($tz)->format($patterns[$fmt] ?? $patterns['dmy']);
    }
}
