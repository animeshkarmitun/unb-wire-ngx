<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryNote;
use App\Models\User;
use App\Repositories\AuditLogRepository;
use Illuminate\Validation\ValidationException;

class NoteService
{
    public function __construct(
        private NotificationService $notifications,
    ) {}

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

        $note = $story->notes()->create([
            'user_id' => $author->id,
            'kind' => $kind,
            'is_internal' => true,
            'body' => $body,
            'created_at' => now(),
        ]);

        $story->events()->create([
            'actor_id' => $author->id,
            'action' => 'note_added',
            'from_status' => $story->status,
            'to_status' => $story->status,
            'payload' => ['note_id' => $note->id],
        ]);

        app(AuditLogRepository::class)->log(
            'note_added',
            'Story',
            $story->id,
            ['note_id' => $note->id],
        );

        // Notify story owner + all prior note authors (excluding current author)
        $participantIds = $story->notes()
            ->where('user_id', '!=', $author->id)
            ->distinct()
            ->pluck('user_id');
        if ($story->owner_id !== $author->id) {
            $participantIds->push($story->owner_id);
        }
        $recipients = User::whereIn('id', $participantIds->unique())->get();
        if ($recipients->isNotEmpty()) {
            $this->notifications->notifyNoteAdded($story->id, $story->headline, $author->id, $recipients);
        }

        return $note;
    }
}
