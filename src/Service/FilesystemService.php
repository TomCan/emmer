<?php

namespace App\Service;

use App\Entity\Bucket;
use App\Entity\File;
use App\Entity\Filepart;
use App\Exception\Bucket\BucketPathExistsException;
use App\Exception\EmmerRuntimeException;
use Symfony\Component\HttpFoundation\Request;

class FilesystemService
{
    public function __construct(
        private string $bucketStoragePath,
        private EncryptionService $encryptionService,
    ) {
    }

    /*
     * Bucket related functions
     */

    public function getBucketPath(Bucket $bucket): string
    {
        if (preg_match('#^(/|\\\\|[a-zA-Z]+:)#', $bucket->getPath())) {
            // absolute path
            return $bucket->getPath();
        } else {
            // relative path from standard storage location
            return $this->bucketStoragePath.DIRECTORY_SEPARATOR.$bucket->getPath();
        }
    }

    public function createBucketPath(Bucket $bucket): void
    {
        $path = $this->getBucketPath($bucket);
        if (file_exists($path)) {
            throw new BucketPathExistsException('Bucket directory '.$path.' already exists');
        } else {
            try {
                mkdir($path, 0775, true);
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to create bucket directory '.$path, 0, $e);
            }
        }
    }

    public function deleteBucketPath(Bucket $bucket): void
    {
        $path = $this->getBucketPath($bucket);
        if (file_exists($path)) {
            try {
                rmdir($path);
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to delete bucket directory '.$path, 0, $e);
            }
        }
    }

    /*
     * Filepart related functions
     */

    public function getFilepartPath(Filepart $filepart): string
    {
        return $this->getBucketPath($filepart->getFile()->getBucket()).DIRECTORY_SEPARATOR.$filepart->getPath();
    }

    public function deleteFilepart(Filepart $filepart): void
    {
        $path = $this->getFilepartPath($filepart);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    /**
     * @param resource|Request|string $source
     */
    public function writeFilepart(Filepart $filepart, mixed $source): int
    {
        $filepartPath = $this->getFilepartPath($filepart);
        $basePath = dirname($filepartPath);
        if (!is_dir($basePath)) {
            mkdir($basePath, 0775, true);
        }

        if (is_resource($source)) {
            $inputResource = $source;
        } elseif ($source instanceof Request) {
            $inputResource = $source->getContent(true);
        } elseif (is_string($source)) {
            try {
                $inputResource = fopen($source, 'rb');
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to open file '.$source, 0, $e);
            }
        } else {
            throw new EmmerRuntimeException('Invalid source type');
        }

        $outputFile = fopen($filepartPath, 'wb');
        if (null == $filepart->getFile()->getDecryptedKey()) {
            $bytesWritten = $this->encryptionService->encryptStream($inputResource, $outputFile, '', 'none');
        } else {
            $bytesWritten = $this->encryptionService->encryptStream($inputResource, $outputFile, $filepart->getFile()->getDecryptedKey());
        }
        fclose($outputFile);

        return $bytesWritten;
    }

    public function mergeFileparts(File $sourceFile, Filepart $targetFilepart): int
    {
        $bytesWritten = 0;

        $outputPath = $this->getFilepartPath($targetFilepart);
        $outputDir = dirname($outputPath);
        // make sure the output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // sort fileparts by part number
        $fileParts = $sourceFile->getFileparts()->toArray();
        usort($fileParts, function (Filepart $a, Filepart $b) {
            return $a->getPartNumber() - $b->getPartNumber();
        });

        $outputFile = fopen($outputPath, 'wb');
        foreach ($fileParts as $sourcePart) {
            $sourcePath = $this->getFilepartPath($sourcePart);
            try {
                $sourceFile = fopen($sourcePath, 'rb');
                $bytesWritten += stream_copy_to_stream($sourceFile, $outputFile);
                fclose($sourceFile);
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to merge fileparts ('.$sourcePath.')', 0, $e);
            }
        }
        fclose($outputFile);

        return $bytesWritten;
    }
}
