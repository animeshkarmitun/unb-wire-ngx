<?php

namespace App\Services;

use App\Models\Story;
use App\Models\StoryVersion;
use App\Models\User;
use App\Repositories\StoryRepository;

class RevisionService
{
    private const SNAPSHOT_FIELDS = [
        'headline',
        'sub_head',
        'brief',
        'body_html',
        'category_id',
        'dateline_city',
        'dateline_at',
        'priority',
        'is_breaking',
        'language',
        'embargo_until',
    ];

    public function __construct(
        private StoryRepository $repo,
    ) {}

    public function snapshot(Story $story, User $actor): StoryVersion
    {
        $snap = [];
        foreach (self::SNAPSHOT_FIELDS as $f) {
            $snap[$f] = $story->$f;
        }
        if (! $story->relationLoaded('tags')) {
            $story->load('tags');
        }
        $snap['tags'] = $story->tags->pluck('name')->all();

        return $this->repo->createVersion($story, $snap, $actor->id);
    }
}
