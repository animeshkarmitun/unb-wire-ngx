<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Services\Delivery\FtpDiskFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use Tests\TestCase;

if (! defined('FTP_BINARY')) {
    define('FTP_BINARY', 2);
}

class FtpDiskFactoryTest extends TestCase
{
    use RefreshDatabase;

    private FtpDiskFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->factory = new FtpDiskFactory;
    }

    private function withProduction(callable $callback): void
    {
        $original = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }

    public function test_it_creates_sftp_adapter_with_password(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'sftp.example.com',
                'username' => 'user1',
                'password' => 'secret',
                'port' => 22,
            ],
        ]);

        $this->withProduction(function () use ($channel) {
            $disk = $this->factory->make($channel);
            $this->assertInstanceOf(FilesystemAdapter::class, $disk);
            $this->assertInstanceOf(SftpAdapter::class, $disk->getAdapter());
        });
    }

    public function test_it_creates_sftp_adapter_with_private_key(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'sftp.example.com',
                'username' => 'user1',
                'privateKey' => 'some-private-key-content',
                'auth_type' => 'sftp',
            ],
        ]);

        $this->withProduction(function () use ($channel) {
            $disk = $this->factory->make($channel);
            $this->assertInstanceOf(FilesystemAdapter::class, $disk);
            $this->assertInstanceOf(SftpAdapter::class, $disk->getAdapter());
        });
    }

    public function test_it_creates_ftp_adapter(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'user2',
                'password' => 'secret',
                'port' => 21,
            ],
        ]);

        $this->withProduction(function () use ($channel) {
            $disk = $this->factory->make($channel);
            $this->assertInstanceOf(FilesystemAdapter::class, $disk);
            $this->assertInstanceOf(FtpAdapter::class, $disk->getAdapter());
        });
    }

    public function test_it_throws_on_invalid_channel_type(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'api',
            'config' => [],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Channel type must be 'ftp'.");

        $this->factory->make($channel);
    }

    public function test_it_throws_on_missing_host(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'username' => 'user1',
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing host in channel config.');

        $this->factory->make($channel);
    }

    public function test_it_throws_on_missing_credentials_for_sftp(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'sftp.example.com',
                'username' => 'user1',
                'port' => 22,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing password or privateKey for SFTP connection.');

        $this->factory->make($channel);
    }

    public function test_it_throws_on_missing_password_for_ftp(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.example.com',
                'username' => 'user1',
                'port' => 21,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing password for FTP connection.');

        $this->factory->make($channel);
    }

    public function test_it_returns_local_adapter_in_non_production(): void
    {
        $client = Client::factory()->create();
        $channel = ClientChannel::factory()->create([
            'client_id' => $client->id,
            'type' => 'ftp',
            'config' => [
                'host' => 'sftp.example.com',
                'username' => 'user1',
                'password' => 'secret',
                'port' => 22,
            ],
        ]);

        $disk = $this->factory->make($channel);
        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(LocalFilesystemAdapter::class, $disk->getAdapter());
    }
}
