<?php

namespace App\Service;

use App\Domain\List\BucketList;
use App\Domain\List\ObjectList;
use App\Entity\Bucket;
use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\Policy;
use App\Entity\User;
use App\Exception\Bucket\BucketExistsException;
use App\Exception\Bucket\BucketNotEmptyException;
use App\Exception\EmmerRuntimeException;
use App\Repository\BucketRepository;
use App\Repository\FilepartRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;

class BucketService
{
    public function __construct(
        private BucketRepository $bucketRepository,
        private FileRepository $fileRepository,
        private FilepartRepository $filepartRepository,
        private EntityManagerInterface $entityManager,
        private GeneratorService $generatorService,
        private PolicyService $policyService,
        private string $bucketStoragePath,
    ) {
    }

    public function getBucket(string $name): ?Bucket
    {
        return $this->bucketRepository->findOneBy(['name' => $name]);
    }

    public function createBucket(string $name, User $user, string $description = '', string $path = '', bool $addDefaultPolicies = true, bool $flush = true): Bucket
    {
        $bucket = $this->getBucket($name);
        if ($bucket) {
            if ($bucket->getOwner() === $user) {
                throw new BucketExistsException('Bucket '.$name.' already exists', 1);
            } else {
                throw new BucketExistsException('Bucket '.$name.' already exists', 0);
            }
        }

        $bucket = new Bucket();
        $bucket->setOwner($user);
        $bucket->setName($name);
        $bucket->setDescription($description);
        if ($path) {
            $bucket->setPath($path);
        } else {
            $bucket->setPath($name);
        }

        $bucketPath = $this->getAbsoluteBucketPath($bucket);
        if (is_dir($bucketPath)) {
            throw new EmmerRuntimeException('Bucket directory already exists');
        } else {
            try {
                mkdir($bucketPath, 0755, true);
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to create bucket directory', 0, $e);
            }
        }

        if ($addDefaultPolicies) {
            $this->policyService->createPolicy(
                'Default owner policy',
                '{"Statement": {"Sid": "BucketOwnerPolicy", "Effect": "Allow", "Principal": ["'.$user->getIdentifier().'"], "Action": ["s3:*"], "Resource": ["'.$bucket->getIdentifier().'", "'.$bucket->getIdentifier().'/*"]}}',
                null,
                $bucket,
            );
        }

        try {
            $this->entityManager->persist($bucket);
            if ($flush) {
                $this->entityManager->flush();
            }

            return $bucket;
        } catch (\Exception $e) {
            throw new EmmerRuntimeException('Failed to create bucket entity', 0, $e);
        }
    }

    public function deleteBucket(Bucket $bucket, bool $flush = true): void
    {
        if (!empty($this->listFiles(bucket: $bucket, maxKeys: 1)->getFiles())) {
            throw new BucketNotEmptyException();
        }

        $path = $this->getAbsoluteBucketPath($bucket);
        if (file_exists($path)) {
            try {
                rmdir($path);
            } catch (\Exception $e) {
                throw new EmmerRuntimeException('Failed to delete bucket directory', 0, $e);
            }
        }

        try {
            $this->entityManager->remove($bucket);
            if ($flush) {
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            throw new EmmerRuntimeException('Failed to delete bucket entity', 0, $e);
        }
    }

    public function getUnusedPath(Bucket $bucket): string
    {
        $path = $this->generatorService->generateId(32, GeneratorService::CLASS_LOWER | GeneratorService::CLASS_NUMBER);
        $path = substr($path, 0, 2).DIRECTORY_SEPARATOR.$path;
        while ($this->filepartRepository->findOneByBucketAndPath($bucket, $path)) {
            $path = $this->generatorService->generateId(32, GeneratorService::CLASS_LOWER | GeneratorService::CLASS_NUMBER);
            $path = substr($path, 0, 2).DIRECTORY_SEPARATOR.$path;
        }

        return $path;
    }

    public function getAbsoluteBucketPath(Bucket $bucket): string
    {
        if (preg_match('#^(/|\\\\|[a-zA-Z]+:)#', $bucket->getPath())) {
            // full path
            return $bucket->getPath();
        } else {
            // relative path from standard storage location
            return $this->bucketStoragePath.DIRECTORY_SEPARATOR.$bucket->getPath();
        }
    }

    public function getAbsolutePartPath(Filepart $filepart): string
    {
        return $this->getAbsoluteBucketPath($filepart->getFile()->getBucket()).DIRECTORY_SEPARATOR.$filepart->getPath();
    }

    public function listOwnBuckets(User $owner, string $prefix, string $marker = '', int $maxItems = 100): BucketList
    {
        $iterator = $this->bucketRepository->findPagedByOwnerAndPrefix($owner, $prefix, $marker, $maxItems + 1);

        // delimiter, return files without delimiter, and group those with delimiter
        $bucketList = new BucketList();
        foreach ($iterator as $bucket) {
            // check if we have reached max-buckets
            if (count($bucketList->getBuckets()) == $maxItems) {
                $bucketList->setTruncated(true);
                $bucketList->setNextMarker($bucket->getName());

                return $bucketList;
            }
            $bucketList->addBucket($bucket);
        }

        return $bucketList;
    }

    public function listFiles(Bucket $bucket, string $prefix = '', string $delimiter = '', string $marker = '', int $markerType = 1, int $maxKeys = 100): ObjectList
    {
        if ($delimiter) {
            // we need to group files, request
            $iterator = $this->fileRepository->findPagedByBucketAndPrefix($bucket, $prefix, $marker, 0, $markerType);
        } else {
            $iterator = $this->fileRepository->findPagedByBucketAndPrefix($bucket, $prefix, $marker, $maxKeys + 1, $markerType);
        }

        // delimiter, return files without delimiter, and group those with delimiter
        $objectList = new ObjectList();
        foreach ($iterator as $file) {
            // check if we have reached max-keys
            if (count($objectList->getFiles()) + count($objectList->getCommonPrefixes()) == $maxKeys) {
                $objectList->setTruncated(true);
                $objectList->setNextMarker($file->getName());

                return $objectList;
            }

            if ($delimiter) {
                $fileName = $file->getName();
                if ('' !== $prefix) {
                    // remove prefix from file name
                    $fileName = substr($fileName, strlen($prefix));
                }
                if (str_contains($fileName, $delimiter)) {
                    // include prefix and delimiter in commonPrefix name
                    $commonPrefix = $prefix.substr($fileName, 0, strrpos($fileName, $delimiter) + strlen($delimiter));
                    if (!$objectList->hasCommonPrefix($commonPrefix)) {
                        $objectList->addCommonPrefix($commonPrefix);
                    }
                } else {
                    // non-delimited file
                    $objectList->addFile($file);
                }
            } else {
                // no delimiter
                $objectList->addFile($file);
            }
        }

        return $objectList;
    }

    public function getFile(Bucket $bucket, string $name): ?File
    {
        return $this->fileRepository->findOneBy(['bucket' => $bucket, 'name' => $name]);
    }

    public function saveFile(File $file, bool $flush = true): void
    {
        $this->entityManager->persist($file);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteFile(File $file, bool $deleteFromStorage = true, bool $flush = true): void
    {
        foreach ($file->getFileparts() as $filepart) {
            $this->deleteFilepart($filepart, $deleteFromStorage, false);
        }

        $this->entityManager->remove($file);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteFilepart(Filepart $filepart, bool $deleteFromStorage = true, bool $flush = true): void
    {
        $path = $this->getAbsolutePartPath($filepart);

        $filepart->setFile(null);
        $this->entityManager->remove($filepart);

        if ($flush) {
            $this->entityManager->flush();
        }

        if ($deleteFromStorage) {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function setBucketPolicy(Bucket $bucket, Policy $policy, bool $flush = false): void
    {
        // unlink all policies from a bucket and attach passed policy
        $this->unlinkBucketPolicies($bucket, false);
        $this->policyService->linkPolicy($policy, $bucket);

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function unlinkBucketPolicies(Bucket $bucket, bool $flush = false): void
    {
        // unlink all policies from a bucket
        foreach ($bucket->getPolicies() as $policy) {
            $this->policyService->unlinkPolicy($policy, $bucket);
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
