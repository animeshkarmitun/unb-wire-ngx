<?php

namespace App\Services\Download;

use App\Models\Client;
use App\Models\Story;
use App\Services\Billing\QuotaService;
use App\Services\Delivery\WireFormatFactory;
use App\Services\Delivery\WireOutput;
use App\Services\Search\EntitlementResolver;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadGateService
{
    public function __construct(
        private EntitlementResolver $entitlementResolver,
        private WireFormatFactory $formatFactory,
        private QuotaService $quotaService,
    ) {}

    /**
     * Verifies client entitlement, checks quota, generates formatted wire file, and ledgers download in DB.
     *
     * @throws AccessDeniedHttpException
     * @throws HttpException
     */
    public function downloadStory(Story $story, Client $client, ?int $clientUserId = null, string $format = 'json-unb-v1'): WireOutput
    {
        // 1. Check entitlement
        $ent = $this->entitlementResolver->forClient($client);

        if (! empty($ent['languages']) && ! in_array($story->language, $ent['languages'], true)) {
            throw new AccessDeniedHttpException('Client is not entitled to stories in this language.');
        }

        if (! empty($ent['category_ids']) && ! in_array($story->category_id, $ent['category_ids'])) {
            throw new AccessDeniedHttpException('Client is not entitled to stories in this category.');
        }

        // 2. Check quota
        $this->quotaService->assertCanDownloadStory($client);

        // 3. Generate wire formatted output
        $output = $this->formatFactory->generate($story, $format);

        // 4. Ledger download in downloads table
        DB::table('downloads')->insert([
            'client_id' => $client->id,
            'client_user_id' => $clientUserId,
            'item_type' => 'story',
            'item_id' => $story->id,
            'format' => $format,
            'size_bytes' => strlen($output->content),
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);

        return $output;
    }
}
