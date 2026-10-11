<?php

namespace App\Console\Scheduling;

use App\Jobs\FanoutStory;
use App\Jobs\ProcessIndexOutbox;
use App\Models\Story;
use App\Support\FeedCache;
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
            FeedCache::bump();
        }

        return $lifted;
    }
}
