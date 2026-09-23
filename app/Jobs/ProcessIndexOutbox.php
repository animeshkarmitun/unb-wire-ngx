<?php

namespace App\Jobs;

use App\Models\Story;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessIndexOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct()
    {
        $this->onQueue('outbox');
    }

    public function handle(): void
    {
        $batch = (int) config('services.meilisearch.batch_size', 50);
        $rows = DB::table('index_outbox')->where('status', 'pending')->orderBy('id')->limit($batch)->get();

        if ($rows->isNotEmpty()) {
            DB::table('index_outbox')->whereIn('id', $rows->pluck('id'))->update(['attempts' => DB::raw('attempts + 1')]);

            $stories = Story::whereIn('public_id', $rows->pluck('document_id'))->get()->keyBy('public_id');
            $host = config('services.meilisearch.host') ?? env('MEILISEARCH_HOST');
            $key = config('services.meilisearch.key') ?? env('MEILISEARCH_KEY');

            foreach ($rows as $row) {
                try {
                    if ($host && $key) {
                        $payload = $this->buildPayload($row, $stories->get($row->document_id));
                        if ($row->op === 'delete') {
                            Http::withHeaders(['Authorization' => "Bearer {$key}"])->delete("{$host}/indexes/{$row->index_name}/documents/{$row->document_id}")->throw();
                        } else {
                            $docs = $payload ? [$payload] : [['id' => $row->document_id, 'objectID' => $row->document_id]];
                            Http::withHeaders(['Authorization' => "Bearer {$key}"])->post("{$host}/indexes/{$row->index_name}/documents", $docs)->throw();
                        }
                    }
                    DB::table('index_outbox')->where('id', $row->id)->update(['status' => 'done', 'processed_at' => now()]);
                } catch (\Throwable $e) {
                    Log::error('index_outbox failed', ['id' => $row->id, 'error' => $e->getMessage()]);
                    $attempts = $row->attempts + 1;
                    DB::table('index_outbox')->where('id', $row->id)->update(['status' => $attempts >= 3 ? 'failed' : 'pending']);
                }
            }
        }
        DB::table('index_outbox')->where('status', 'done')->where('processed_at', '<', now()->subDays(7))->delete();
    }

    private function buildPayload(object $row, ?Story $story): ?array
    {
        if (! in_array($row->index_name, ['main', 'archive']) || ! $story) {
            return null;
        }

        return [
            'id' => $story->public_id,
            'objectID' => $story->public_id,
            'headline' => $story->headline,
            'brief' => $story->brief,
            'body_text' => $story->body_text,
            'language' => $story->language,
            'category_id' => $story->category_id,
            'published_at' => $story->published_at?->toIso8601String(),
            'is_breaking' => $story->is_breaking,
            'status' => $story->status,
        ];
    }

    public static function lagSeconds(): ?int
    {
        $oldest = DB::table('index_outbox')->where('status', 'pending')->min('created_at');

        return $oldest ? now()->diffInSeconds($oldest) : 0;
    }
}
