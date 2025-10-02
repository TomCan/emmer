<?php

namespace App\Service;

class EncryptionService
{
    public function __construct(
        private string $key = '',
        private string $cipher = 'aes-256-cbc',
    ) {
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function setCipher(string $cipher): void
    {
        $this->cipher = $cipher;
    }

    public function encryptString(string $data, bool $raw = true): string
    {
        if ('none' == $this->cipher) {
            if ($raw) {
                return $data;
            } else {
                return base64_encode($data);
            }
        }

        if (!in_array(strtolower($this->cipher), array_map('strtolower', openssl_get_cipher_methods()))) {
            throw new \RuntimeException('Cipher not supported\n'.implode(' ', openssl_get_cipher_methods()));
        }

        // generate random iv
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $ciphertext = openssl_encrypt($data, $this->cipher, $this->key, $options = 0, $iv);

        if ($raw) {
            // return as binary data
            return $iv.$ciphertext;
        }

        // return hex encoded
        return base64_encode($iv.$ciphertext);
    }

    public function decryptString(string $data, bool $raw = true): string
    {
        if ('none' == $this->cipher) {
            if ($raw) {
                return $data;
            } else {
                return base64_decode($data);
            }
        }

        if ($raw) {
            $bin = $data;
        } else {
            $bin = base64_decode($data);
        }

        $iv_length = openssl_cipher_iv_length($this->cipher);
        $iv = substr($bin, 0, $iv_length);
        $encrypted = substr($bin, $iv_length);

        return openssl_decrypt($encrypted, $this->cipher, $this->key, 0, $iv);
    }
}
