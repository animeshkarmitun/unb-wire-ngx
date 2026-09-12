<?php

namespace Tests\Feature;

use App\Services\Delivery\WebhookSigner;
use Tests\TestCase;

class WebhookSignerTest extends TestCase
{
    public function test_sign_produces_correct_hmac()
    {
        $signer = new WebhookSigner();
        $payload = json_encode(['foo' => 'bar']);
        $secret = 'test_secret';
        $timestamp = 1234567890;
        
        $signature = $signer->sign($payload, $secret, $timestamp);
        
        $expected = hash_hmac('sha256', '1234567890.' . $payload, $secret);
        
        $this->assertEquals($expected, $signature);
    }

    public function test_headers_format()
    {
        $signer = new WebhookSigner();
        $payload = json_encode(['foo' => 'bar']);
        $secret = 'test_secret';
        $event = 'story.published';
        
        $headers = $signer->headers($payload, $secret, $event);
        
        $this->assertArrayHasKey('X-UNB-Signature', $headers);
        $this->assertArrayHasKey('X-UNB-Timestamp', $headers);
        $this->assertArrayHasKey('X-UNB-Event', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
        $this->assertEquals($event, $headers['X-UNB-Event']);
        $this->assertStringStartsWith('sha256=', $headers['X-UNB-Signature']);
    }
}
