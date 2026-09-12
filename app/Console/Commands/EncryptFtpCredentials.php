<?php

namespace App\Console\Commands;

use App\Models\ClientChannel;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EncryptFtpCredentials extends Command
{
    protected $signature = 'delivery:encrypt-credentials';
    protected $description = 'Encrypt plaintext FTP/SFTP passwords in client_channels config';

    public function handle()
    {
        $channels = ClientChannel::where('type', 'ftp')->get();
        $encryptedCount = 0;
        $skippedCount = 0;

        foreach ($channels as $channel) {
            $config = $channel->config ?? [];
            if (!isset($config['password']) || $config['password'] === '') {
                $skippedCount++;
                continue;
            }

            try {
                Crypt::decryptString($config['password']);
                // It's already encrypted
                $skippedCount++;
            } catch (DecryptException $e) {
                // It's plaintext
                $config['password'] = Crypt::encryptString($config['password']);
                $channel->config = $config;
                $channel->save();
                $encryptedCount++;
            }
        }

        $this->info("Encrypted {$encryptedCount} passwords. Skipped {$skippedCount}.");
    }
}
