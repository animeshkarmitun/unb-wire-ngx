<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\Delivery;
use App\Models\Download;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotaService
{
    /**
     * Returns quotas and current calendar-month usage for a client.
     */
    public function getUsage(Client $client): array
    {
        $notes = is_string($client->notes) ? json_decode($client->notes, true) : (is_array($client->notes) ? $client->notes : []);
        $tierQuotas = $notes['tier_quotas'] ?? [];

        $storiesQuota = isset($tierQuotas['stories_quota']) ? (int) $tierQuotas['stories_quota'] : null;
        $mediaQuota = isset($tierQuotas['media_quota']) ? (int) $tierQuotas['media_quota'] : null;

        $now = now();
        $deliveriesCount = Delivery::where('client_id', $client->id)
            ->where('deliverable_type', 'story')
            ->where('status', 'sent')
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $storyDownloadsCount = Download::where('client_id', $client->id)
            ->where('item_type', 'story')
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        $storiesUsed = $deliveriesCount + $storyDownloadsCount;

        $mediaUsed = Download::where('client_id', $client->id)
            ->where('item_type', 'media')
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->count();

        return [
            'stories_quota' => $storiesQuota,
            'stories_used' => $storiesUsed,
            'media_quota' => $mediaQuota,
            'media_used' => $mediaUsed,
        ];
    }

    public function canDownloadMedia(Client $client): bool
    {
        $usage = $this->getUsage($client);

        if ($usage['media_quota'] === null) {
            return true;
        }

        return $usage['media_used'] < $usage['media_quota'];
    }

    public function canDownloadStory(Client $client): bool
    {
        $usage = $this->getUsage($client);

        if ($usage['stories_quota'] === null) {
            return true;
        }

        return $usage['stories_used'] < $usage['stories_quota'];
    }

    /**
     * @throws HttpException
     */
    public function assertCanDownloadMedia(Client $client): void
    {
        if (! $this->canDownloadMedia($client)) {
            throw new HttpException(429, 'Monthly media download quota exceeded.');
        }
    }

    /**
     * @throws HttpException
     */
    public function assertCanDownloadStory(Client $client): void
    {
        if (! $this->canDownloadStory($client)) {
            throw new HttpException(429, 'Monthly story quota exceeded.');
        }
    }
}
