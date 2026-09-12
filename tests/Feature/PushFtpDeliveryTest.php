<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\PushFtpDelivery;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\Delivery;
use App\Models\Package;
use App\Models\Story;
use App\Services\Delivery\FtpDiskFactory;
use App\Services\Delivery\WireFormatFactory;
use App\Services\Delivery\WireOutput;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PushFtpDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_fanout_dispatches_push_ftp_delivery()
    {
        Queue::fake();

        $client = Client::factory()->create(['status' => 'active']);
        $package = Package::factory()->create([
            'entitlement_filter' => ['languages' => ['en']],
        ]);
        ClientPackage::factory()->create(['client_id' => $client->id, 'package_id' => $package->id, 'status' => 'active']);

        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'user',
                'password' => 'pass',
                'wire_format' => 'json-unb-v1',
            ],
        ]);

        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);

        $job = new FanoutStory($story->id);
        $job->handle();

        Queue::assertPushed(PushFtpDelivery::class, function ($job) use ($channel) {
            $delivery = Delivery::find($job->deliveryId);
            return $delivery && $delivery->channel_id === $channel->id;
        });
    }

    public function test_push_ftp_delivery_uploads_file_and_updates_status()
    {
        $client = Client::factory()->create(['status' => 'active']);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'user',
                'password' => 'pass',
                'wire_format' => 'json-unb-v1',
            ],
        ]);

        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);

        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'queued',
            'attempt_count' => 0,
            'idempotency_key' => 'test-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $diskMock = Mockery::mock(FilesystemAdapter::class);
        $diskMock->shouldReceive('put')
            ->once()
            ->with('story.json', '{"content": "test"}')
            ->andReturn(true);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldReceive('make')
            ->once()
            ->andReturn($diskMock);

        $this->app->instance(FtpDiskFactory::class, $factoryMock);

        $wireMock = Mockery::mock(WireFormatFactory::class);
        $wireMock->shouldReceive('generate')
            ->once()
            ->andReturn(new WireOutput('{"content": "test"}', 'story.json', 'application/json'));

        $this->app->instance(WireFormatFactory::class, $wireMock);

        $job = new PushFtpDelivery($delivery->id);
        $job->handle(app(\App\Repositories\ClientRepository::class), $wireMock, $factoryMock);

        $delivery->refresh();
        $this->assertEquals('sent', $delivery->status);
        $this->assertNotNull($delivery->sent_at);
        
        $channel->refresh();
        $this->assertEquals(0, $channel->failure_count);
        $this->assertNotNull($channel->last_success_at);
    }

    public function test_push_ftp_delivery_records_failure()
    {
        $client = Client::factory()->create(['status' => 'active']);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'user',
                'password' => 'pass',
                'wire_format' => 'json-unb-v1',
            ],
        ]);

        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);

        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'queued',
            'attempt_count' => 0,
            'idempotency_key' => 'test-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldReceive('make')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(FtpDiskFactory::class, $factoryMock);

        $job = new PushFtpDelivery($delivery->id);
        
        try {
            $job->handle(app(\App\Repositories\ClientRepository::class), app(WireFormatFactory::class), $factoryMock);
        } catch (\Exception $e) {
            $this->assertEquals('Connection failed', $e->getMessage());
        }

        $delivery->refresh();
        $this->assertEquals('queued', $delivery->status);
        $this->assertEquals(1, $delivery->attempt_count);
        $this->assertEquals('Connection failed', $delivery->error);
        
        $channel->refresh();
        $this->assertEquals(1, $channel->failure_count);
    }
}
