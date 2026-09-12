<?php

namespace App\Services\Delivery;

use App\Models\Story;

class TriggerMatcher
{
    /**
     * Determine if a webhook should fire for a given story based on triggers.
     * If no triggers are configured, default to true (backward compat).
     * If triggers are configured, story must match at least one active trigger (OR logic).
     */
    public function shouldFire(Story $story, array $triggers): bool
    {
        // Filter to only active triggers
        $activeTriggers = array_filter($triggers);

        // If no triggers are configured or all are false, default to firing on all
        if (empty($activeTriggers)) {
            return true;
        }

        if (isset($activeTriggers['breaking']) && $story->is_breaking) {
            return true;
        }

        if (isset($activeTriggers['media_pack']) && $story->media->isNotEmpty()) {
            return true;
        }

        if (isset($activeTriggers['exclusive']) && ($story->getAttribute('is_exclusive') || $story->tags->contains('slug', 'exclusive'))) {
            return true;
        }

        if (isset($activeTriggers['embargoed']) && $story->embargo_until !== null) {
            return true;
        }

        return false;
    }
}
