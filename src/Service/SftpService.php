<?php

namespace App\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

class SftpService
{
    private string $key;

    public function __construct(string $appSecret)
    {
        $this->key = substr(hash('sha256', $appSecret, true), 0, 32);
    }

    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $plaintext,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encoded): string
    {
        $decoded   = base64_decode($encoded);
        $iv        = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $result = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($result === false) {
            throw new \RuntimeException('No se pudo descifrar la contraseña SFTP.');
        }

        return $result;
    }

    public function connect(
        string $host,
        int $port,
        string $username,
        string $encryptedPassword,
        string $rootPath
    ): Filesystem {
        $password = $this->decrypt($encryptedPassword);

        // LOG TEMPORAL - quitar después
        error_log('PASSWORD DESENCRIPTADA: [' . $password . ']');

        $provider = new SftpConnectionProvider(
            $host,
            $username,
            $password,
            null,
            $port
        );

        $adapter = new SftpAdapter($provider, $rootPath);
        return new Filesystem($adapter);
    }
}