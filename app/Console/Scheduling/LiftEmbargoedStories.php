<?php

namespace App\Console\Scheduling;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Client;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LiftEmbargoedStories
{
    public function handle(): array
    {
        $lifted = [];

        Story::query()
            ->where('embargo_until', '<=', now())
            ->where('status', 'approved')
            ->cursor()
            ->each(function (Story $s) use (&$lifted) {
                DB::transaction(function () use ($s) {
                    $s->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);
                    DB::table('index_outbox')->insert([
                        'index_name' => 'main',
                        'op' => 'upsert',
                        'document_id' => $s->public_id,
                        'status' => 'pending',
                        'attempts' => 0,
                        'created_at' => now(),
                    ]);
                });

                FanoutStory::dispatch($s->id);
                ProcessIndexOutbox::dispatch();

                $lifted[] = $s->public_id;
            });

        if (! empty($lifted)) {
            $this->invalidatePortalFeedCache();
        }

        return $lifted;
    }

    public function invalidatePortalFeedCache(): int
    {
        $cleared = 0;
        $knownBases = [
            url('/api/v1/feed'),
            'http://localhost:8000/api/v1/feed',
            'http://127.0.0.1:8000/api/v1/feed',
        ];

        Client::query()->where('status', 'active')->each(function (Client $client) use ($knownBases, &$cleared) {
            foreach ($knownBases as $base) {
                $key = 'feed:v1:'.$client->id.':'.md5($base);
                if (Cache::forget($key)) {
                    $cleared++;
                }
            }
        });

        return $cleared;
    }
}