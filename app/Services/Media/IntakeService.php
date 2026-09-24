<?php

namespace App\Services\Media;

use App\Models\MediaAsset;
use App\Models\MediaBatch;
use App\Models\UploadSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IntakeService
{
    public function ingest(string $sessionId): MediaAsset
    {
        $session = UploadSession::findOrFail($sessionId);
        $tmpPath = Storage::disk('local')->path("uploads/{$sessionId}");

        $checksum = hash_file('sha256', $tmpPath);
        $ext = strtolower(pathinfo($session->filename, PATHINFO_EXTENSION)) ?: 'bin';
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
        $dest = 'media/uploads/'.Str::ulid().'.'.$ext;
        $stream = fopen($tmpPath, 'rb');
        Storage::disk('public')->put($dest, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $batch = MediaBatch::create([
            'uploader_id' => $session->user_id,
            'event_label' => Str::limit((string) $session->filename, 160),
            'urgency' => 'routine',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $asset = MediaAsset::create([
            'public_id' => (string) Str::ulid(),
            'batch_id' => $batch->id,
            'kind' => str_starts_with($mime, 'video/') ? 'video' : 'photo',
            'status' => 'field',
            'source' => 'field',
            'title' => Str::limit((string) $session->filename, 120),
            'caption' => '',
            'credit_line' => '',
            'mime' => $mime,
            'size_bytes' => (int) $session->size_bytes,
            'checksum' => (string) $checksum,
            'storage_disk' => 'public',
            'original_path' => $dest,
            'uploaded_by' => $session->user_id,
        ]);

        Storage::disk('local')->delete("uploads/{$sessionId}");

        return $asset;
    }
}
