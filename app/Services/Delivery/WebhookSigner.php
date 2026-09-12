<?php

namespace App\Services\Delivery;

class WebhookSigner
{
    public function sign(string $jsonPayload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $jsonPayload, $secret);
    }

    public function headers(string $jsonPayload, string $secret, string $event): array
    {
        $timestamp = time();
        $signature = $this->sign($jsonPayload, $secret, $timestamp);

        return [
            'X-UNB-Signature' => 'sha256=' . $signature,
            'X-UNB-Timestamp' => $timestamp,
            'X-UNB-Event' => $event,
            'Content-Type' => 'application/json',
        ];
    }
}
