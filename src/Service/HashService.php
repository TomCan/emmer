<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\Filepart;

class HashService
{
    public function __construct(
        private BucketService $bucketService,
    ) {
    }

    /**
     * Create a file hash of the entire file, even if the file consists of multiple fileparts.
     */
    public function hashFile(File $file, string $algorithm = 'md5'): string
    {
        $bucketPath = $this->bucketService->getAbsoluteBucketPath($file->getBucket());
        $hashContext = hash_init($algorithm);

        foreach ($file->getFileparts() as $filepart) {
            hash_update_file($hashContext, $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath());
        }

        return hash_final($hashContext);
    }

    public function hashFilepart(Filepart $filepart, string $algorithm = 'md5'): string
    {
        $bucketPath = $this->bucketService->getAbsoluteBucketPath($filepart->getFile()->getBucket());

        return hash_file($algorithm, $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath());
    }
}
