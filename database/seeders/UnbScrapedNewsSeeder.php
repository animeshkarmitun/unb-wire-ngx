<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use App\Support\FeedCache;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UnbScrapedNewsSeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = __DIR__.'/data/unb_scraped_50.json';
        if (! file_exists($jsonPath)) {
            $this->command->error("Data file not found at {$jsonPath}");

            return;
        }

        $items = json_decode(file_get_contents($jsonPath), true);
        if (! is_array($items) || empty($items)) {
            $this->command->error("No items found in {$jsonPath}");

            return;
        }

        $user = User::first() ?? User::factory()->create([
            'name' => 'UNB Newsroom Desk',
            'email' => 'desk@unb.com.bd',
        ]);

        // Category map
        $bangladeshCat = Category::where('slug', 'bangladesh')->first() ?? Category::first();
        $worldCat = Category::where('slug', 'world')->first() ?? $bangladeshCat;
        $sportsCat = Category::where('slug', 'sports')->first() ?? $bangladeshCat;
        $businessCat = Category::where('slug', 'business')->first() ?? $bangladeshCat;
        $opinionCat = Category::where('slug', 'opinion')->first() ?? $bangladeshCat;
        $lifestyleCat = Category::where('slug', 'lifestyle')->first() ?? $bangladeshCat;

        $seededCount = 0;
        $now = Carbon::now();

        foreach ($items as $idx => $item) {
            $catRaw = strtolower($item['category_raw'] ?? '');
            $category = match (true) {
                str_contains($catRaw, 'world') => $worldCat,
                str_contains($catRaw, 'sport') => $sportsCat,
                str_contains($catRaw, 'business') || str_contains($catRaw, 'econom') => $businessCat,
                str_contains($catRaw, 'opinion') => $opinionCat,
                str_contains($catRaw, 'life') || str_contains($catRaw, 'tech') || str_contains($catRaw, 'entertain') || str_contains($catRaw, 'science') => $lifestyleCat,
                default => $bangladeshCat,
            };

            // Distribute published_at nicely over past 24 hours
            $publishedAt = (clone $now)->subMinutes(($idx + 1) * 20);

            // Check if story with same headline already exists
            $story = Story::updateOrCreate(
                ['headline' => $item['headline']],
                [
                    'public_id' => (string) Str::ulid(),
                    'language' => 'en',
                    'status' => 'published',
                    'sub_head' => null,
                    'brief' => Str::limit($item['brief'] ?? '', 275),
                    'body_html' => $item['body_html'] ?? '<p>Story content.</p>',
                    'body_text' => $item['body_text'] ?? 'Story content.',
                    'category_id' => $category->id,
                    'dateline_city' => 'Dhaka',
                    'dateline_at' => $publishedAt,
                    'published_at' => $publishedAt,
                    'is_breaking' => ! empty($item['is_breaking']),
                    'priority' => $item['priority'] ?? 'routine',
                    'source' => 'wire',
                    'owner_id' => $user->id,
                    'created_by' => $user->id,
                    'version' => 1,
                    'word_count' => str_word_count(strip_tags($item['body_text'] ?? '')),
                ]
            );

            $seededCount++;
        }

        // Invalidate feed cache epoch so all clients see fresh 50 news immediately
        FeedCache::bump();

        $this->command->info("Successfully seeded {$seededCount} UNB stories from unb.com.bd and bumped FeedCache epoch!");
    }
}
