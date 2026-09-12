<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Services\Delivery\FtpDiskFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use League\Flysystem\Ftp\FtpAdapter;
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

    public function test_it_creates_sftp_adapter_with_password()
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
        $this->assertInstanceOf(SftpAdapter::class, $disk->getAdapter());
    }

    public function test_it_creates_sftp_adapter_with_private_key()
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

        $disk = $this->factory->make($channel);

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(SftpAdapter::class, $disk->getAdapter());
    }

    public function test_it_creates_ftp_adapter()
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

        $disk = $this->factory->make($channel);

        $this->assertInstanceOf(FilesystemAdapter::class, $disk);
        $this->assertInstanceOf(FtpAdapter::class, $disk->getAdapter());
    }

    public function test_it_throws_on_invalid_channel_type()
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

    public function test_it_throws_on_missing_host()
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

    public function test_it_throws_on_missing_credentials_for_sftp()
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

    public function test_it_throws_on_missing_password_for_ftp()
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
}
