<?php

namespace App\Tests\Service\EncryptionService;

use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceStringTest extends TestCase
{
    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        $this->encryptionService = new EncryptionService();
    }

    // String Encryption Tests

    public function testEncryptString(): void
    {
        $data = 'Hello World!';
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key);

        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($data, $encrypted);
    }

    public function testEncryptStringWithBase64Output(): void
    {
        $data = 'Hello World!';
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key, 'aes-256-cbc', false);

        $this->assertNotEmpty($encrypted);
        // Should be valid base64
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function testEncryptStringWithNoneCipher(): void
    {
        $data = 'Hello World!';
        $key = 'test-key-32-char-long-for-aes256';

        // Test with raw output
        $encryptedRaw = $this->encryptionService->encryptString($data, $key, 'none', true);
        $this->assertEquals($data, $encryptedRaw);

        // Test with base64 output
        $encryptedB64 = $this->encryptionService->encryptString($data, $key, 'none', false);
        $this->assertEquals(base64_encode($data), $encryptedB64);
    }

    public function testEncryptStringWithUnsupportedCipher(): void
    {
        $data = 'Hello World!';
        $key = 'test-key-32-char-long-for-aes256';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cipher not supported');

        $this->encryptionService->encryptString($data, $key, 'tom-256-can');
    }

    public function testEmptyStringEncryption(): void
    {
        $data = '';
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key);
        $decrypted = $this->encryptionService->decryptString($encrypted, $key);

        $this->assertEquals($data, $decrypted);
    }

    public function testLargeStringEncryption(): void
    {
        $data = str_repeat('Hello World! ', 10000); // +100KB
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key);
        $decrypted = $this->encryptionService->decryptString($encrypted, $key);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptionWithBinaryData(): void
    {
        $data = pack('N*', 0x12345678, 0x9ABCDEF0, 0x11223344);
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key);
        $decrypted = $this->encryptionService->decryptString($encrypted, $key);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptionWithUnicodeData(): void
    {
        $data = 'Test with émojis 🔒 and ünicode çharacters';
        $key = 'test-key-32-char-long-for-aes256';

        $encrypted = $this->encryptionService->encryptString($data, $key);
        $decrypted = $this->encryptionService->decryptString($encrypted, $key);

        $this->assertEquals($data, $decrypted);
    }
}
