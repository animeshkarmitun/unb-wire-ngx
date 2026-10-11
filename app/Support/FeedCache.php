<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class FeedCache
{
    public const EPOCH_KEY = 'feed:epoch';

    /** Seconds a feed entry is served fresh; publishes bump the epoch so freshness is event-driven, not TTL-driven. */
    public const FRESH = 30;

    /** Seconds a feed entry may be served stale while one request regenerates it (stale-while-revalidate). */
    public const STALE = 120;

    public static function epoch(): int
    {
        return (int) Cache::get(self::EPOCH_KEY, 0);
    }

    public static function bump(): void
    {
        Cache::increment(self::EPOCH_KEY);
    }

    public static function portalKey(int|string $clientId, string $urlHash): string
    {
        return 'portal:feed:'.self::epoch().':'.$clientId.':'.$urlHash;
    }

    public static function clientKey(int|string $clientId, string $urlHash): string
    {
        return 'feed:v1:'.self::epoch().':'.$clientId.':'.$urlHash;
    }
}
