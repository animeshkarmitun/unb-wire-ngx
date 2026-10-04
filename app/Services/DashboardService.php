<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Story;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\StoryRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardService
{
    public function __construct(
        private StoryRepository $stories,
        private ClientRepository $clients,
        private DeliveryRepository $deliveries,
    ) {}

    /**
     * Get dashboard view data including live KPIs, deltas, recent stories, and top clients.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        $today = Carbon::today('Asia/Dhaka');
        $yesterday = Carbon::yesterday('Asia/Dhaka');
        $startOfWeek = Carbon::now('Asia/Dhaka')->startOfWeek();
        $sevenDaysAgo = Carbon::now('Asia/Dhaka')->subDays(7);
        $fourteenDaysAgo = Carbon::now('Asia/Dhaka')->subDays(14);

        // 1–4. KPI aggregates (cached — see M13-PERF-001)
        $kpis = Cache::remember('dashboard:kpis', now()->addSeconds(60), function () use ($today, $yesterday, $startOfWeek, $sevenDaysAgo, $fourteenDaysAgo) {
            // 1. Stories published today & delta vs yesterday
            $publishedToday = $this->stories->countByDateAndStatus($today, 'published');
            $publishedYesterday = $this->stories->countByDateAndStatus($yesterday, 'published');
            $deltaPublished = $publishedToday - $publishedYesterday;

            // 2. Active clients & delta this week
            $activeClients = $this->clients->activeCount();
            $clientsThisWeek = $this->clients->activeClientsThisWeek($startOfWeek);

            // 3. Distribution success rate (last 7 days vs previous 7 days)
            $recentDel = $this->deliveries->recentCount($sevenDaysAgo);
            $recentDelOk = $this->deliveries->recentDeliveredCount($sevenDaysAgo);
            $successRate = $recentDel > 0 ? round(($recentDelOk / $recentDel) * 100, 1) : 98.4;

            $priorDel = $this->deliveries->countBetween($fourteenDaysAgo, $sevenDaysAgo);
            $priorDelOk = $this->deliveries->deliveredCountBetween($fourteenDaysAgo, $sevenDaysAgo);
            $priorRate = $priorDel > 0 ? round(($priorDelOk / $priorDel) * 100, 1) : 98.7;
            $deltaRate = round($successRate - $priorRate, 1);

            // 4. Exclusive content sent (breaking, urgent/flash priority, or tagged exclusive)
            $exclusiveToday = $this->stories->countExclusive($today);
            $exclusiveYesterday = $this->stories->countExclusive($yesterday);
            $deltaExclusive = $exclusiveToday - $exclusiveYesterday;

            return [
                'publishedToday' => $publishedToday,
                'deltaPublishedText' => ($deltaPublished >= 0 ? '+' : '').$deltaPublished.' from yesterday',
                'deltaPublishedDirection' => $deltaPublished >= 0 ? 'up' : 'down',
                'activeClients' => $activeClients,
                'deltaClientsText' => '+'.$clientsThisWeek.' this week',
                'deltaClientsDirection' => 'up',
                'successRate' => $successRate,
                'deltaRateText' => ($deltaRate >= 0 ? '+' : '').$deltaRate.'% from last week',
                'deltaRateDirection' => $deltaRate >= 0 ? 'up' : 'down',
                'exclusiveToday' => $exclusiveToday,
                'deltaExclusiveText' => ($deltaExclusive >= 0 ? '+' : '').$deltaExclusive.' from yesterday',
                'deltaExclusiveDirection' => $deltaExclusive >= 0 ? 'up' : 'down',
            ];
        });

        // 5. Recent stories (4 items, eager loaded)
        $recentStories = $this->stories->recentPublished(4);

        // Decorate stories with presentation attributes
        $decoratedStories = $recentStories->map(function (Story $story) {
            $totalDel = $story->deliveries->count();
            $deliveredDel = $story->deliveries->where('status', 'delivered')->count();
            $isDistributed = $totalDel > 0 && $deliveredDel === $totalDel;
            $distPercent = $totalDel > 0 ? round(($deliveredDel / $totalDel) * 100) : ($story->status === 'published' ? 100 : 0);

            $tags = [];
            $isExclusive = $story->is_breaking || in_array($story->priority, ['urgent', 'flash'], true) || $story->tags->contains(fn ($t) => in_array($t->slug, ['exclusive', 'special'], true));
            if ($isExclusive) {
                $tags[] = ['name' => 'Exclusive', 'class' => 'exclusive'];
            }
            if ($story->tags->isNotEmpty()) {
                foreach ($story->tags as $tag) {
                    if (strtolower($tag->name) === 'exclusive') {
                        continue;
                    }
                    $tags[] = ['name' => $tag->name, 'class' => 'standard'];
                }
            }
            if (empty($tags)) {
                $tags[] = ['name' => 'Standard', 'class' => 'standard'];
            }

            return [
                'id' => $story->id,
                'public_id' => $story->public_id,
                'headline' => $story->headline,
                'meta' => ($story->language === 'bn' ? 'Bangla' : 'English').' · '.($story->category?->name_en ?? 'General').' · '.($story->word_count ?? 300).' words',
                'tags' => $tags,
                'status' => $story->status,
                'is_distributed' => $isDistributed,
                'dist_percent' => $distPercent,
            ];
        });

        // 6. Top clients today (3 items)
        $clients = $this->clients->topClients($today, 3);

        $avatarTints = ['crimson', 'blue', 'amber'];
        $decoratedClients = $clients->map(function (Client $client, int $index) use ($avatarTints) {
            $words = preg_split('/\s+/', trim($client->name));
            $initials = '';
            if (count($words) >= 2) {
                $initials = strtoupper(substr($words[0], 0, 1).substr($words[1], 0, 1));
            } else {
                $initials = strtoupper(substr($client->name, 0, 2));
            }

            $primaryPkg = $client->clientPackages->first()?->package?->name ?? 'Standard';
            $tier = str_contains(strtolower($primaryPkg), 'premium') ? 'Premium' : (str_contains(strtolower($primaryPkg), 'basic') ? 'Basic' : 'Standard');
            $scopeDesc = $tier === 'Premium' ? 'All content' : ($tier === 'Basic' ? 'Headlines only' : 'Text only');
            $downloadCount = $client->downloads_today_count ?: $client->downloads_count ?: 0;

            return [
                'name' => $client->name,
                'initials' => $initials,
                'tint' => $avatarTints[$index % 3],
                'tier' => $tier,
                'downloads' => $downloadCount,
                'scope' => $scopeDesc,
            ];
        });

        return array_merge($kpis, [
            'recentStories' => $decoratedStories,
            'topClients' => $decoratedClients,
        ]);
    }
}
