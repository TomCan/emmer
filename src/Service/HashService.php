<?php

namespace App\Service;

use App\Entity\File;
use App\Entity\Filepart;

class HashService
{
    /**
     * Create a file hash of the entire file, even if the file consists of multiple fileparts.
     */
    public function hashFile(File $file, string $bucketPath, string $algorithm = 'md5'): string
    {
        $hashContext = hash_init($algorithm);

        foreach ($file->getFileparts() as $filepart) {
            hash_update_file($hashContext, $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath());
        }

        return hash_final($hashContext);
    }

    public function hashFilepart(Filepart $filepart, string $bucketPath, string $algorithm = 'md5'): string
    {
        return hash_file($algorithm, $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath());
    }

    /*
     * Instead of hashing the entire file, we can hash the hashes of the fileparts.
     */
    public function hashFileFilepartHashes(File $file, string $algorithm = 'md5'): string
    {
        $hashContext = hash_init($algorithm);

        foreach ($file->getFileparts() as $filepart) {
            hash_update($hashContext, $filepart->getEtag());
        }

        return hash_final($hashContext);
    }
}
