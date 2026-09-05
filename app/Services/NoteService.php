<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryNote;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class NoteService
{
    public function add(Story $story, User $author, string $body, ?string $kind = null): StoryNote
    {
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 2000) {
            throw ValidationException::withMessages([
                'body' => 'Note body must be between 1 and 2000 characters.',
            ]);
        }

        if (! $kind) {
            $kind = 'note';
        }

        return $story->notes()->create([
            'user_id' => $author->id,
            'kind' => $kind,
            'is_internal' => true,
            'body' => $body,
            'created_at' => now(),
        ]);
    }
}
