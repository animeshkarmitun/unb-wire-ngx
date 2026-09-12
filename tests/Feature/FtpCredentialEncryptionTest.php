<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientChannel;
use App\Services\ClientService;
use App\Services\Delivery\FtpDiskFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class FtpCredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_ftp_credentials_are_encrypted_on_save()
    {
        $client = Client::factory()->create();
        app(ClientService::class)->saveFtpCredentials($client, 'ftp.test', 'user', '21', 'secret123');

        $channel = $client->clientChannels()->where('type', 'ftp')->first();
        $this->assertNotNull($channel);

        $password = $channel->config['password'];
        $this->assertNotEquals('secret123', $password);
        $this->assertEquals('secret123', Crypt::decryptString($password));
    }

    public function test_ftp_disk_factory_decrypts_password_at_runtime()
    {
        $channel = ClientChannel::factory()->create([
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.test',
                'username' => 'user',
                'port' => '21',
                'password' => Crypt::encryptString('secret123'),
            ],
        ]);

        $factory = new FtpDiskFactory;
        $disk = $factory->make($channel);

        $this->assertNotNull($disk);
        $this->assertEquals('secret123', $disk->getConfig()['password']);
    }

    public function test_migration_command_encrypts_plaintext_passwords()
    {
        $channel = ClientChannel::factory()->create([
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.test',
                'username' => 'user',
                'port' => '21',
                'password' => 'plaintext456',
            ],
        ]);

        Artisan::call('delivery:encrypt-credentials');

        $channel->refresh();
        $password = $channel->config['password'];
        $this->assertNotEquals('plaintext456', $password);
        $this->assertEquals('plaintext456', Crypt::decryptString($password));
    }

    public function test_migration_command_is_idempotent()
    {
        $encrypted = Crypt::encryptString('secret123');
        $channel = ClientChannel::factory()->create([
            'type' => 'ftp',
            'config' => [
                'host' => 'ftp.test',
                'username' => 'user',
                'port' => '21',
                'password' => $encrypted,
            ],
        ]);

        Artisan::call('delivery:encrypt-credentials');

        $channel->refresh();
        $this->assertEquals($encrypted, $channel->config['password']);
    }
}
