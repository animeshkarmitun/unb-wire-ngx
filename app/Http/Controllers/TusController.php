<?php

namespace App\Http\Controllers;

use App\Http\Requests\TusCreateRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TusController extends Controller
{
    public function create(TusCreateRequest $request)
    {
        $id = (string) Str::uuid();
        DB::table('upload_sessions')->insert([
            'id' => $id,
            'user_id' => $request->user()->id,
            'kind' => $request->input('kind', 'photo'),
            'filename' => $request->input('filename', 'upload.bin'),
            'size_bytes' => (int) ($request->header('Upload-Length') ?? $request->input('upload_length')),
            'offset_bytes' => 0,
            'status' => 'active',
            'meta' => json_encode(['filename' => $request->input('filename', 'upload')]),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response('', 201)->header('Location', url("/api/uploads/{$id}"))->header('Tus-Resumable', '1.0.0');
    }

    public function head(Request $request, string $id)
    {
        $s = DB::table('upload_sessions')->where('id', $id)->first();
        if (! $s) {
            abort(404);
        }
        if ((int) $s->user_id !== $request->user()->id) {
            abort(403);
        }

        return response('', 200)->header('Upload-Offset', (string) $s->offset_bytes)->header('Upload-Length', (string) $s->size_bytes)->header('Tus-Resumable', '1.0.0');
    }

    public function patch(Request $request, string $id)
    {
        $s = DB::table('upload_sessions')->where('id', $id)->first();
        if (! $s) {
            abort(404);
        }
        if ((int) $s->user_id !== $request->user()->id) {
            abort(403);
        }
        $offset = (int) $request->header('Upload-Offset', 0);
        if ($offset !== (int) $s->offset_bytes) {
            return response('Conflict', 409)->header('Tus-Resumable', '1.0.0');
        }
        $chunk = $request->getContent();
        $newOffset = $offset + strlen($chunk);
        DB::table('upload_sessions')->where('id', $id)->update(['offset_bytes' => $newOffset, 'updated_at' => now()]);
        if ($newOffset >= (int) $s->size_bytes) {
            DB::table('upload_sessions')->where('id', $id)->update(['status' => 'completed']);
        }

        return response('', 204)->header('Upload-Offset', (string) $newOffset)->header('Tus-Resumable', '1.0.0');
    }
}
