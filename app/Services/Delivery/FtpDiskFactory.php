<?php

namespace App\Services\Delivery;

use App\Models\ClientChannel;
use Illuminate\Filesystem\FilesystemAdapter;
use InvalidArgumentException;
use League\Flysystem\Filesystem;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

class FtpDiskFactory
{
    public function make(ClientChannel $channel): FilesystemAdapter
    {
        if ($channel->type !== 'ftp') {
            throw new InvalidArgumentException("Channel type must be 'ftp'.");
        }

        $config = $channel->config ?? [];
        
        $host = $config['host'] ?? null;
        $username = $config['username'] ?? null;
        
        if (empty($host)) {
            throw new InvalidArgumentException("Missing host in channel config.");
        }
        if (empty($username)) {
            throw new InvalidArgumentException("Missing username in channel config.");
        }

        $port = (int) ($config['port'] ?? 21);
        $authType = $config['auth_type'] ?? ($port === 22 ? 'sftp' : 'ftp');
        
        $password = $config['password'] ?? null;
        $privateKey = $config['privateKey'] ?? null;
        $passphrase = $config['passphrase'] ?? null;

        if ($authType === 'sftp') {
            $providerOptions = [
                'host' => $host,
                'username' => $username,
                'port' => $port,
            ];
            
            if ($privateKey) {
                $providerOptions['privateKey'] = $privateKey;
                if ($passphrase) {
                    $providerOptions['passphrase'] = $passphrase;
                }
            } elseif ($password) {
                $providerOptions['password'] = $password;
            } else {
                throw new InvalidArgumentException("Missing password or privateKey for SFTP connection.");
            }

            $provider = SftpConnectionProvider::fromArray($providerOptions);
            $adapter = new SftpAdapter($provider, '/');
        } else {
            if (!$password) {
                throw new InvalidArgumentException("Missing password for FTP connection.");
            }
            $options = FtpConnectionOptions::fromArray([
                'host' => $host,
                'root' => '/',
                'username' => $username,
                'password' => $password,
                'port' => $port,
                'passive' => $config['passive'] ?? true,
                'ssl' => $config['ssl'] ?? false,
                'timeout' => 30,
            ]);
            $adapter = new FtpAdapter($options);
        }

        $driver = new Filesystem($adapter);
        
        return new FilesystemAdapter($driver, $adapter, $config);
    }
}
