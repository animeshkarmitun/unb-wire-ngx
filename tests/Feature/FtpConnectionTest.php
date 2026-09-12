<?php

namespace Tests\Feature;

use App\Livewire\Admin\DeliverySettings;
use App\Models\Client;
use App\Models\ClientChannel;
use App\Models\User;
use App\Services\ClientService;
use App\Services\Delivery\FtpDiskFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Livewire\Livewire;
use Tests\TestCase;
use Mockery;

class FtpConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;
    protected ClientChannel $ftpChannel;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->admin = User::where('email', 'nahar@unbnews.org')->firstOrFail();

        $this->client = Client::first();
        if (!$this->client) {
            $this->client = Client::factory()->create();
        }
        
        $this->ftpChannel = ClientChannel::firstOrCreate([
            'client_id' => $this->client->id,
            'type' => 'ftp',
        ], [
            'status' => 'active',
            'failure_count' => 0,
            'config' => [
                'host' => 'ftp.test.com',
                'username' => 'testuser',
                'password' => 'secret',
                'port' => '21',
            ],
        ]);
    }

    public function test_delivery_settings_test_connection_success()
    {
        $mockDisk = Mockery::mock(FilesystemAdapter::class);
        $mockDisk->shouldReceive('write')->once()->andReturn(true);
        $mockDisk->shouldReceive('delete')->once()->andReturn(true);

        $mockFactory = Mockery::mock(FtpDiskFactory::class);
        $mockFactory->shouldReceive('make')->once()->andReturn($mockDisk);
        
        $this->app->instance(FtpDiskFactory::class, $mockFactory);

        Livewire::actingAs($this->admin)
            ->test(DeliverySettings::class)
            ->set('selectedClientId', $this->client->id)
            ->call('testConnection')
            ->assertDispatched('toast')
            ->assertSet('testBtnText', '✓ Connection OK');

        $this->ftpChannel->refresh();
        $this->assertEquals(0, $this->ftpChannel->failure_count);
        $this->assertNotNull($this->ftpChannel->last_success_at);
        $this->assertEquals('ok', $this->ftpChannel->config['health']);
    }

    public function test_delivery_settings_test_connection_failure()
    {
        $mockFactory = Mockery::mock(FtpDiskFactory::class);
        $mockFactory->shouldReceive('make')->once()->andThrow(new \Exception('Connection refused'));
        
        $this->app->instance(FtpDiskFactory::class, $mockFactory);

        Livewire::actingAs($this->admin)
            ->test(DeliverySettings::class)
            ->set('selectedClientId', $this->client->id)
            ->call('testConnection')
            ->assertDispatched('toast')
            ->assertSet('testBtnText', '✗ Failed');

        $this->ftpChannel->refresh();
        $this->assertEquals(1, $this->ftpChannel->failure_count);
        $this->assertEquals('fail', $this->ftpChannel->config['health']);
    }

    public function test_client_service_test_ftp_connection_success()
    {
        $mockDisk = Mockery::mock(FilesystemAdapter::class);
        $mockDisk->shouldReceive('write')->once()->andReturn(true);
        $mockDisk->shouldReceive('delete')->once()->andReturn(true);

        $mockFactory = Mockery::mock(FtpDiskFactory::class);
        $mockFactory->shouldReceive('make')->once()->andReturn($mockDisk);
        
        $this->app->instance(FtpDiskFactory::class, $mockFactory);

        $service = new ClientService();
        $service->testFtpConnection($this->client);

        $this->ftpChannel->refresh();
        $this->assertEquals(0, $this->ftpChannel->failure_count);
        $this->assertNotNull($this->ftpChannel->last_success_at);
        $this->assertEquals('ok', $this->ftpChannel->config['health']);

        $this->client->refresh();
        $this->assertStringContainsString('FTP connection test passed', $this->client->notes);
    }

    public function test_client_service_test_ftp_connection_failure()
    {
        $mockFactory = Mockery::mock(FtpDiskFactory::class);
        $mockFactory->shouldReceive('make')->once()->andThrow(new \Exception('Timeout'));
        
        $this->app->instance(FtpDiskFactory::class, $mockFactory);

        $service = new ClientService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Timeout');

        try {
            $service->testFtpConnection($this->client);
        } finally {
            $this->ftpChannel->refresh();
            $this->assertEquals(1, $this->ftpChannel->failure_count);
            $this->assertEquals('fail', $this->ftpChannel->config['health']);

            $this->client->refresh();
            $this->assertStringContainsString('FTP connection failed', $this->client->notes);
        }
    }
}
