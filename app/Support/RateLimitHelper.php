<?php

namespace App\Support;

class RateLimitHelper
{
    public static function attempts(string $name): int
    {
        $base = config("rate-limiting.limits.{$name}.attempts", 60);

        if (config('rate-limiting.enabled') === false) {
            return $base;
        }

        if (! app()->isProduction()) {
            return (int) ($base * config('rate-limiting.dev_multiplier', 1));
        }

        return $base;
    }

    public static function decay(string $name): int
    {
        return config("rate-limiting.limits.{$name}.decay", 1);
    }

    public static function disabled(): bool
    {
        return config('rate-limiting.enabled') === false;
    }
}
