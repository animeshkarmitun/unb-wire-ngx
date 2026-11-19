<?php

namespace Tests\Feature;

use App\Jobs\FanoutStory;
use App\Jobs\PushFtpDelivery;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\ClientPackage;
use App\Models\ClientUser;
use App\Models\Delivery;
use App\Models\Package;
use App\Models\Story;
use App\Repositories\ClientRepository;
use App\Services\Delivery\FtpDiskFactory;
use App\Services\Delivery\WireFormatFactory;
use App\Services\Delivery\WireOutput;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $job->handle(app(ClientRepository::class), $wireMock, $factoryMock);

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
            $job->handle(app(ClientRepository::class), app(WireFormatFactory::class), $factoryMock);
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

    public function test_permanent_failure_marks_delivery_failed_on_final_attempt()
    {
        // COS-75: after the last try the distribution log must show Failed,
        // not an eternally queued row.
        $client = Client::factory()->create(['status' => 'active']);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'p'],
        ]);
        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);
        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'queued',
            'attempt_count' => 2,
            'idempotency_key' => 'final-attempt-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldReceive('make')->once()->andThrow(new \Exception('Connection failed'));

        $job = new PushFtpDelivery($delivery->id);
        try {
            $job->handle(app(ClientRepository::class), app(WireFormatFactory::class), $factoryMock);
        } catch (\Exception) {
            // expected rethrow on final attempt
        }

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(3, $delivery->attempt_count);
    }

    public function test_permanent_failure_notifies_client_portal_users()
    {
        // COS-77 follow-up: the client's portal users must learn which story
        // did not reach their FTP channel so they can pull it from the portal.
        $client = Client::factory()->create(['status' => 'active']);
        $portalUser = ClientUser::factory()->create([
            'client_id' => $client->id,
            'status' => 'active',
        ]);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => ['host' => 'ftp.example.com', 'username' => 'u', 'password' => 'p'],
        ]);
        $story = Story::factory()->create(['status' => 'published', 'language' => 'en', 'headline' => 'FTP fail probe']);
        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'queued',
            'attempt_count' => 2,
            'idempotency_key' => 'notify-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldReceive('make')->once()->andThrow(new \Exception('Connection failed'));

        $job = new PushFtpDelivery($delivery->id);
        try {
            $job->handle(app(ClientRepository::class), app(WireFormatFactory::class), $factoryMock);
        } catch (\Exception) {
            // expected rethrow on final attempt
        }

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => ClientUser::class,
            'notifiable_id' => $portalUser->id,
        ]);
        $row = DB::table('notifications')->where('notifiable_id', $portalUser->id)->first();
        $payload = json_decode($row->data, true);
        $this->assertEquals('story.ftp_failed', $payload['event']);
        $this->assertEquals($story->public_id, $payload['public_id']);
        $this->assertStringContainsString('FTP delivery failed', $payload['title']);
        $this->assertStringContainsString('from the portal', $payload['message']);
    }

    public function test_non_final_failure_does_not_notify()
    {
        // Only the permanent (final-attempt) failure notifies — retries stay silent.
        $client = Client::factory()->create(['status' => 'active']);
        ClientUser::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => ['host' => 'ftp.example.com'],
        ]);
        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);
        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'queued',
            'attempt_count' => 0,
            'idempotency_key' => 'retry-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldReceive('make')->once()->andThrow(new \Exception('Connection failed'));

        $job = new PushFtpDelivery($delivery->id);
        try {
            $job->handle(app(ClientRepository::class), app(WireFormatFactory::class), $factoryMock);
        } catch (\Exception) {
            // expected rethrow on retryable attempt
        }

        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => ClientUser::class,
        ]);
    }

    public function test_terminal_delivery_is_skipped_by_duplicate_jobs()
    {
        // A delivery already failed (or sent) must not be re-pushed or
        // re-notified by a replayed/duplicate queue job.
        $client = Client::factory()->create(['status' => 'active']);
        ClientUser::factory()->create(['client_id' => $client->id, 'status' => 'active']);
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'status' => 'active',
            'config' => ['host' => 'ftp.example.com'],
        ]);
        $story = Story::factory()->create(['status' => 'published', 'language' => 'en']);
        $delivery = Delivery::create([
            'deliverable_type' => 'story',
            'deliverable_id' => $story->id,
            'client_id' => $client->id,
            'channel_id' => $channel->id,
            'status' => 'failed',
            'attempt_count' => 3,
            'idempotency_key' => 'terminal-key',
            'payload_hash' => 'hash',
            'created_at' => now(),
        ]);

        $factoryMock = Mockery::mock(FtpDiskFactory::class);
        $factoryMock->shouldNotReceive('make');

        (new PushFtpDelivery($delivery->id))->handle(
            app(ClientRepository::class),
            app(WireFormatFactory::class),
            $factoryMock
        );

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(3, $delivery->attempt_count);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => ClientUser::class,
        ]);
    }
}
