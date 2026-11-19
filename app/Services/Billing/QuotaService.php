<?php

namespace App\Services\Billing;

use App\Models\Client;
use App\Models\ClientEntitlementOverride;
use App\Models\Delivery;
use App\Models\Download;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class QuotaService
{
    /**
     * Returns quotas and current calendar-month usage for a client.
     */
    public function getUsage(Client $client): array
    {
        $limits = $this->getQuotaLimits($client);
        $storiesQuota = $limits['stories_quota'];
        $mediaQuota = $limits['media_quota'];

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

    /**
     * Resolve package quota union + explicit per-client overrides.
     * Add-on packages grant access, but do not increase base quotas.
     *
     * @return array{stories_quota: int|null, media_quota: int|null}
     */
    public function getQuotaLimits(Client $client): array
    {
        $rows = DB::table('client_packages')
            ->join('packages', 'packages.id', '=', 'client_packages.package_id')
            ->where('client_packages.client_id', $client->id)
            ->where('client_packages.status', 'active')
            ->where(function ($q) {
                $q->whereNull('client_packages.starts_at')->orWhere('client_packages.starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('client_packages.ends_at')->orWhere('client_packages.ends_at', '>', now());
            })
            ->get(['packages.stories_quota', 'packages.media_quota', 'packages.entitlement_filter']);

        $basePackages = $rows->filter(function ($row) {
            $filter = is_string($row->entitlement_filter)
                ? json_decode($row->entitlement_filter, true)
                : (array) $row->entitlement_filter;

            return empty($filter['is_addon']);
        });

        $storiesQuota = $this->unionQuota($basePackages->pluck('stories_quota')->all());
        $mediaQuota = $this->unionQuota($basePackages->pluck('media_quota')->all());

        $override = ClientEntitlementOverride::where('client_id', $client->id)->first();
        if ($override?->override_stories_quota) {
            $storiesQuota = $override->stories_quota;
        }
        if ($override?->override_media_quota) {
            $mediaQuota = $override->media_quota;
        }

        return [
            'stories_quota' => $storiesQuota,
            'media_quota' => $mediaQuota,
        ];
    }

    /**
     * Union quotas: any unlimited base package makes the union unlimited;
     * otherwise clients receive the highest numeric limit.
     *
     * @param  array<int, mixed>  $values
     */
    private function unionQuota(array $values): ?int
    {
        if ($values === [] || in_array(null, $values, true)) {
            return null;
        }

        return max(array_map(static fn ($value) => (int) $value, $values));
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
