<?php

namespace App\Tests\Service\EncryptionService;

use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceStreamTest extends TestCase
{
    private EncryptionService $encryptionService;
    private string $inputFile;
    private string $inputFileHash;
    private string $key = 'test-key-32-char-long-for-aes256';

    /**
     * @var string[]
     */
    private array $usedFiles;

    protected function setUp(): void
    {
        $this->encryptionService = new EncryptionService();

        // generate 10MB random file
        $this->inputFile = tempnam(sys_get_temp_dir(), 'encrypt');
        $fp = fopen($this->inputFile, 'wb');
        for ($i = 0; $i < 10; ++$i) {
            fwrite($fp, random_bytes(1024 * 1024));
        }
        fclose($fp);
        $this->inputFileHash = hash_file('md5', $this->inputFile);

        $this->usedFiles = [$this->inputFile];
    }

    // Stream Encryption Tests

    public function testEncryptStream(): void
    {
        $outputFile1 = tempnam(sys_get_temp_dir(), 'encrypt');
        $this->usedFiles[] = $outputFile1;

        $inputStream = fopen($this->inputFile, 'r');
        $outputStream = fopen($outputFile1, 'w');

        $this->encryptionService->encryptStream($inputStream, $outputStream, $this->key);
        fclose($inputStream);
        fclose($outputStream);

        $outputHash = hash_file('md5', $outputFile1);
        $this->assertNotEquals($outputHash, $this->inputFileHash);
    }

    public function testEncryptStreamNone(): void
    {
        $outputFile1 = tempnam(sys_get_temp_dir(), 'encrypt');
        $this->usedFiles[] = $outputFile1;

        $inputStream = fopen($this->inputFile, 'r');
        $outputStream = fopen($outputFile1, 'w');

        $this->encryptionService->encryptStream($inputStream, $outputStream, $this->key, 'none');
        fclose($inputStream);
        fclose($outputStream);

        $outputHash = hash_file('md5', $outputFile1);
        $this->assertEquals($outputHash, $this->inputFileHash);
    }

    public function testDecryptStreamRoundTrip(): void
    {
        $outputFile1 = tempnam(sys_get_temp_dir(), 'encrypt');
        $this->usedFiles[] = $outputFile1;

        $inputStream = fopen($this->inputFile, 'r');
        $outputStream = fopen($outputFile1, 'w');
        $this->encryptionService->encryptStream($inputStream, $outputStream, $this->key);
        fclose($inputStream);
        fclose($outputStream);
        $outputHash1 = hash_file('md5', $outputFile1);

        $outputFile2 = tempnam(sys_get_temp_dir(), 'encrypt');
        $this->usedFiles[] = $outputFile2;

        $inputStream = fopen($outputFile1, 'r');
        $outputStream = fopen($outputFile2, 'w');
        $this->encryptionService->decryptStream($inputStream, $outputStream, $this->key);
        fclose($inputStream);
        fclose($outputStream);

        $outputHash2 = hash_file('md5', $outputFile2);
        $this->assertNotEquals($outputHash1, $this->inputFileHash);
        $this->assertEquals($outputHash2, $this->inputFileHash);
    }

    public function testDecryptStreamWithInvalidIV(): void
    {
        // Create stream with insufficient IV data
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, 'short'); // Less than IV length
        rewind($inputStream);

        $outputStream = fopen('php://memory', 'w+');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read IV or incomplete IV');

        $this->encryptionService->decryptStream($inputStream, $outputStream, $this->key);

        fclose($inputStream);
        fclose($outputStream);
    }

    // IV Counter Tests

    public function testIncrementIvCounter(): void
    {
        $iv = str_repeat("\x00", 16); // 16 zero bytes
        $result = $this->encryptionService->incrementIvCounter($iv, 16); // 1 block

        $this->assertNotEquals($iv, $result);
        $this->assertEquals(16, strlen($result));

        // Check that only the last 8 bytes changed
        $this->assertEquals(substr($iv, 0, 8), substr($result, 0, 8));
        $this->assertNotEquals(substr($iv, 8), substr($result, 8));
    }

    public function testIncrementIvCounterMultipleBlocks(): void
    {
        $iv = str_repeat("\x00", 16);
        $result1 = $this->encryptionService->incrementIvCounter($iv, 32); // 2 blocks
        $result2 = $this->encryptionService->incrementIvCounter($iv, 48); // 3 blocks

        $this->assertNotEquals($result1, $result2);
        $this->assertEquals(16, strlen($result1));
        $this->assertEquals(16, strlen($result2));
    }

    public function testIncrementIvCounterWithPartialBlock(): void
    {
        $iv = str_repeat("\x00", 16);
        $result = $this->encryptionService->incrementIvCounter($iv, 10); // Less than 1 block, should round up

        $this->assertNotEquals($iv, $result);
        $this->assertEquals(16, strlen($result));
    }

    protected function tearDown(): void
    {
        foreach ($this->usedFiles as $file) {
            try {
                if (file_exists($file)) {
                    unlink($file);
                }
            } catch (\Exception $e) {
                // ignore
            }
        }
    }
}
