<?php

namespace App\Service;

class EncryptionService
{
    public function encryptString(string $data, string $key, string $cipher = 'aes-256-cbc', bool $raw = true): string
    {
        if ('none' == $cipher) {
            if ($raw) {
                return $data;
            } else {
                return base64_encode($data);
            }
        }

        if (!in_array($cipher, array_map('strtolower', openssl_get_cipher_methods()))) {
            throw new \RuntimeException('Cipher not supported\n'.implode(' ', openssl_get_cipher_methods()));
        }

        // generate random iv
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);

        $ciphertext = openssl_encrypt($data, $cipher, $key, $options = 0, $iv);

        if ($raw) {
            // return as binary data
            return $iv.$ciphertext;
        }

        // return hex encoded
        return base64_encode($iv.$ciphertext);
    }

    public function decryptString(string $data, string $key, string $cipher = 'aes-256-cbc', bool $raw = true): string
    {
        if ('none' == $cipher) {
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

        $iv_length = openssl_cipher_iv_length($cipher);
        $iv = substr($bin, 0, $iv_length);
        $encrypted = substr($bin, $iv_length);

        return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
    }

    /**
     * @param resource $inputStream
     * @param resource $outputStream
     */
    public function encryptStream(mixed $inputStream, mixed $outputStream, string $key, string $cipher = 'aes-256-ctr', int $chunkSize = 65536): void
    {
        if ('none' == $cipher) {
            stream_copy_to_stream($inputStream, $outputStream);

            return;
        }

        // generate random iv
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $currentIv = $iv;

        // Write IV to output stream first
        if (false === fwrite($outputStream, $iv)) {
            throw new \RuntimeException('Failed to write IV to output stream');
        }

        // Process input stream in chunks
        while (!feof($inputStream)) {
            $chunk = fread($inputStream, $chunkSize);

            if (false === $chunk) {
                throw new \RuntimeException('Failed to read from input stream');
            }

            if (0 === strlen($chunk)) {
                break;
            }

            // Encrypt chunk with current IV
            $encryptedChunk = openssl_encrypt(
                $chunk,
                $cipher,
                $key,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $currentIv
            );

            if (false === $encryptedChunk) {
                throw new \RuntimeException('Encryption failed: '.openssl_error_string());
            }

            // Write encrypted chunk
            if (false === fwrite($outputStream, $encryptedChunk)) {
                throw new \RuntimeException('Failed to write encrypted chunk to output stream');
            }

            // Increment IV counter for next chunk
            $currentIv = $this->incrementIvCounter($currentIv, strlen($chunk));
        }
    }

    /**
     * @param resource $inputStream
     * @param resource $outputStream
     */
    public function decryptStream(mixed $inputStream, mixed $outputStream, string $key, string $cipher = 'aes-256-ctr', int $chunkSize = 65536): void
    {
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = fread($inputStream, $ivlen);
        if (strlen($iv) !== $ivlen) {
            throw new \RuntimeException('Failed to read IV or incomplete IV');
        }
        $currentIv = $iv;

        // Process encrypted stream in chunks
        while (!feof($inputStream)) {
            $encryptedChunk = fread($inputStream, $chunkSize);

            if (false === $encryptedChunk) {
                throw new \RuntimeException('Failed to read from input stream');
            }

            if (0 === strlen($encryptedChunk)) {
                break;
            }

            // Decrypt chunk (CTR decryption is identical to encryption)
            $decryptedChunk = openssl_decrypt(
                $encryptedChunk,
                $cipher,
                $key,
                OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
                $currentIv
            );

            if (false === $decryptedChunk) {
                throw new \RuntimeException('Decryption failed: '.openssl_error_string());
            }

            // Write decrypted chunk
            if (false === fwrite($outputStream, $decryptedChunk)) {
                throw new \RuntimeException('Failed to write decrypted chunk to output stream');
            }

            // Increment IV counter for next chunk
            $currentIv = $this->incrementIvCounter($currentIv, strlen($encryptedChunk));
        }
    }

    public function incrementIvCounter(string $iv, int $bytes): string
    {
        // Calculate number of AES blocks processed (AES block size = 16 bytes)
        $blocksProcessed = intval(ceil($bytes / 16));

        // Treat the last 8 bytes of IV as a big-endian counter
        $counter = 0;
        for ($i = 8; $i < 16; ++$i) {
            $counter = ($counter << 8) + ord($iv[$i]);
        }

        // Increment counter
        $counter += $blocksProcessed;

        // Convert back to 8 bytes, big-endian
        $counterBytes = '';
        for ($i = 7; $i >= 0; --$i) {
            $counterBytes = chr($counter & 0xFF).$counterBytes;
            $counter >>= 8;
        }

        // Return first 8 bytes of original iv + new counter (last 8 bytes)
        return substr($iv, 0, 8).$counterBytes;
    }
}
