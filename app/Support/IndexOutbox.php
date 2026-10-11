<?php

namespace App\Support;

use App\Jobs\ProcessIndexOutbox;
use Illuminate\Support\Facades\DB;

class IndexOutbox
{
    /**
     * Enqueue a search-index op with pending-row dedupe — autosave bursts collapse
     * to one pending row per (index, document, op): the worker builds payloads from
     * current story state, so the last write wins. Dispatches the processor to run
     * after the response (NFR §1: index at write time, never from the request path).
     */
    public static function enqueue(string $indexName, string $documentId, string $op = 'upsert'): void
    {
        $exists = DB::table('index_outbox')
            ->where('index_name', $indexName)
            ->where('document_id', $documentId)
            ->where('op', $op)
            ->where('status', 'pending')
            ->exists();

        if (! $exists) {
            DB::table('index_outbox')->insert([
                'index_name' => $indexName,
                'op' => $op,
                'document_id' => $documentId,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => now(),
            ]);
        }

        ProcessIndexOutbox::dispatch()->afterResponse();
    }
}
