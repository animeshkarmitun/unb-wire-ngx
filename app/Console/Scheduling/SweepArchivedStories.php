<?php

namespace App\Console\Scheduling;

use App\Jobs\ProcessIndexOutbox;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

class SweepArchivedStories
{
    public function handle(): array
    {
        $archived = [];

        Story::query()
            ->where('status', 'published')
            ->where('published_at', '<', now()->subMonths(12))
            ->chunkById(100, function ($stories) use (&$archived) {
                foreach ($stories as $s) {
                    DB::transaction(function () use ($s) {
                        $s->update(['status' => 'archived']);
                        DB::table('index_outbox')->insert([
                            [
                                'index_name' => 'main',
                                'op' => 'delete',
                                'document_id' => $s->public_id,
                                'status' => 'pending',
                                'attempts' => 0,
                                'created_at' => now(),
                            ],
                            [
                                'index_name' => 'archive',
                                'op' => 'upsert',
                                'document_id' => $s->public_id,
                                'status' => 'pending',
                                'attempts' => 0,
                                'created_at' => now(),
                            ],
                        ]);
                    });
                    $archived[] = $s->public_id;
                }
                ProcessIndexOutbox::dispatch();
            });

        return $archived;
    }
}