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
use App\Exception\Object\InvalidManifestException;
use App\Repository\BucketRepository;
use App\Repository\FilepartRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BucketService
{
    public function __construct(
        private BucketRepository $bucketRepository,
        private FileRepository $fileRepository,
        private FilepartRepository $filepartRepository,
        private EntityManagerInterface $entityManager,
        private GeneratorService $generatorService,
        private PolicyService $policyService,
        private HashService $hashService,
        private AuthorizationService $authorizationService,
        private string $bucketStoragePath,
        private bool $mergeMultipartUploads,
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

    public function saveFileAndParts(File $file, bool $flush = false): void
    {
        foreach ($file->getFileparts() as $filepart) {
            $this->entityManager->persist($filepart);
        }
        $this->entityManager->persist($file);

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

    /**
     * @param resource $inputResource
     */
    public function createFilePartFromResource(File $file, int $partNumber, mixed $inputResource): Filepart
    {
        // multipart upload exists
        $filePart = new Filepart();
        $filePart->setPartNumber($partNumber);
        $filePart->setName($this->generatorService->generateId(32));
        $filePart->setPath($this->getUnusedPath($file->getBucket()));
        $file->addFilepart($filePart);

        $outputPath = $this->getAbsolutePartPath($filePart);
        $basePath = dirname($outputPath);
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $outputFile = fopen($outputPath, 'wb');
        $bytesWritten = stream_copy_to_stream($inputResource, $outputFile);
        fclose($outputFile);

        $file->setMtime(new \DateTime());
        $filePart->setMtime($file->getMtime());
        $filePart->setSize($bytesWritten);
        $filePart->setEtag($this->hashService->hashFilepart($filePart, $this->getAbsoluteBucketPath($file->getBucket())));

        return $filePart;
    }

    public function createMultipartUpload(Bucket $bucket, string $key, string $contentType = ''): File
    {
        $id = $this->generatorService->generateId(64);
        $file = new File();
        $file->setBucket($bucket);
        $file->setName('{emmer:mpu:'.$id.'}'.$key);
        $file->setMtime(new \DateTime());
        $file->setSize(0);
        $file->setEtag('');
        $file->setContentType($contentType);
        $this->saveFile($file);

        // once saved, abuse the Etag field to store multipart upload id
        $file->setEtag($id);

        return $file;
    }

    public function completeMultipartUpload(File $file, \SimpleXMLElement $manifest): File
    {
        $bucket = $file->getBucket();
        // remove {emmer:mpu:xxxx} prefix, just find first }
        $key = substr($file->getName(), strpos((string) $file->getName(), '}') + 1);

        if ('CompleteMultipartUpload' !== $manifest->getName()) {
            throw new InvalidManifestException('Provided XML is not a valid CreateMultipartUpload manifest', 0);
        }

        $parts = [];
        $fileParts = $file->getFileparts()->toArray();
        ksort($fileParts);
        foreach ($manifest->Part as $part) {
            $partNumber = (int) $part->PartNumber;
            if ($partNumber !== count($parts) + 1) {
                throw new InvalidManifestException('Invalid part order', 1);
            }
            if (!isset($fileParts[$partNumber - 1])) {
                throw new InvalidManifestException('Part '.$partNumber.' not found', 2);
            }

            $etag = (string) $part->ETag;
            if (str_starts_with($etag, '"') && str_ends_with($etag, '"')) {
                $etag = substr($etag, 1, -1);
            }
            if ('' !== $etag && $fileParts[$partNumber - 1]->getEtag() !== $etag) {
                throw new InvalidManifestException('Part '.$partNumber.' ETag does not match', 3);
            }

            $parts[] = $fileParts[$partNumber - 1];
        }

        // we have all parts in the order we need them. We can proceed.

        // Start transaction as we need to flush multiple times due to UnitOfWork order
        $this->entityManager->beginTransaction();

        // check if this is a new file or existing file
        $targetFile = $this->getFile($file->getBucket(), $key);
        if (null == $targetFile) {
            // new file
            $targetFile = new File();
            $targetFile->setBucket($bucket);
            $targetFile->setName($key);
            $targetFile->setSize(0);
            $targetFile->setMtime(new \DateTime());
            $targetFile->setEtag('');
            $this->saveFile($targetFile, false);
        } else {
            // existing file, delete existing parts
            foreach ($targetFile->getFileparts() as $part) {
                // known issue, if anything goes wrong the parts will be left in the database, but not on disk
                // if we ever support versions, we will fix this by creating a new version and deleting the old one afterwards
                $this->deleteFilepart($part, true, false);
            }
            $targetFile->getFileparts()->clear();
            // need to save and flush, as otherwise a unique key violation will occur
            $this->saveFile($targetFile);
        }

        // flush state of File without any Fileparts
        $this->entityManager->flush();

        $bucketPath = $this->getAbsoluteBucketPath($bucket);
        if ($this->mergeMultipartUploads) {
            // merge parts into single part
            $targetPart = new Filepart();
            $targetFile->addFilepart($targetPart);
            $targetPart->setPartNumber(1);
            $targetPart->setName($this->generatorService->generateId(32));
            $targetPart->setPath($this->getUnusedPath($bucket));

            $outputPath = $this->getAbsolutePartPath($targetPart);
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputFile = fopen($outputPath, 'wb');
            foreach ($parts as $part) {
                $partPath = $this->getAbsolutePartPath($part);
                $partFile = fopen($partPath, 'rb');
                stream_copy_to_stream($partFile, $outputFile);
                fclose($partFile);
            }
            fclose($outputFile);

            // calculate md5 hash of merged file
            $targetPart->setEtag($this->hashService->hashFilepart($targetPart, $bucketPath));
            $targetPart->setSize(filesize($outputPath));
            $targetPart->setMtime(new \DateTime());

            // use same info for file, no need to recalculate
            $targetFile->setSize($targetPart->getSize());
            $targetFile->setEtag($targetPart->getEtag());
            $targetFile->setMtime($targetPart->getMtime());
        } else {
            // keep separate parts. Link them to the target file.
            foreach ($parts as $part) {
                $file->removeFilepart($part);
                $targetFile->addFilepart($part);
            }

            // calculate md5 hash of the File across all parts
            $targetFile->setEtag($this->hashService->hashFile($targetFile, $bucketPath));
            $targetFile->setSize($this->calculateFileSize($targetFile));
            $targetFile->setMtime(new \DateTime());

            // flush state of the old file without parts and the new file with the parts
            $this->entityManager->flush();
        }

        // finally, delete the old file, then save the new one to prevent duplicate keys
        $this->deleteFile($file, true, true);
        $this->saveFileAndParts($targetFile, true);

        // should be done, commit transaction
        $this->entityManager->commit();

        return $targetFile;
    }

    public function calculateFileSize(File $file): int
    {
        $size = 0;
        foreach ($file->getFileparts() as $filepart) {
            $size += $filepart->getSize();
        }

        return $size;
    }

    /**
     * @return array{'DeleteResult': mixed[]}
     */
    public function deleteObjects(Bucket $bucket, User $user, \SimpleXMLElement $manifest): array
    {
        // must be Delete request
        if ('Delete' !== $manifest->getName()) {
            throw new InvalidManifestException('Provided XML is not a valid DeleteObjects manifest', 0);
        }

        if (0 === count($manifest->Object)) {
            // must have at least one Object
            throw new InvalidManifestException('Empty DeleteObjects manifest', 1);
        }

        $objects = [];
        foreach ($manifest->Object as $object) {
            $objects[] = (string) $object->Key;
        }

        $quiet = 'true' === (string) ($manifest->Quiet ?? 'false');
        $deleted = [];
        $errors = [];
        foreach ($manifest->Object as $object) {
            if (!$object->Key) {
                throw new InvalidManifestException('Invalid Object in DeleteObjects manifest', 2);
            }

            // check if user has permissions to delete this object
            try {
                $this->authorizationService->requireAll(
                    $user,
                    [['action' => 's3:DeleteObject', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$object->Key]],
                    $bucket
                );
            } catch (AccessDeniedException $e) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'AccessDenied',
                    'Message' => 'Access Denied.',
                    // VersionId
                ];
                continue;
            }

            $file = $this->getFile($bucket, (string) $object->Key);
            if (!$file) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'NoSuchKey',
                    'Message' => 'The specified key does not exist.',
                    // VersionId
                ];
                continue;
            }

            // check if ETag is present and matches
            if ($object->ETag && $file->getEtag() !== (string) $object->ETag) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'ETagMismatch',
                    'Message' => 'ETag Mismatch',
                    // VersionId
                ];
                continue;
            }

            // check if LastModifiedTime is present
            if ($object->LastModifiedTime && $file->getMtime() !== new \DateTime((string) $object->LastModifiedTime)) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'LastModifiedTimeMismatch',
                    'Message' => 'LastModifiedTime Mismatch',
                    // VersionId
                ];
                continue;
            }

            if ($object->Size && $file->getSize() !== (int) $object->Size) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'SizeMismatch',
                    'Message' => 'Size Mismatch',
                    // VersionId
                ];
                continue;
            }

            // ready to delete
            try {
                $this->deleteFile($file, true, true);

                if (!$quiet) {
                    $deleted[] = [
                        'Key' => (string) $object->Key,
                        'DeleteMarker' => 'false', // unsupported
                        // VersionId
                    ];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'FilesystemError',
                    'Message' => 'Filesystem Error',
                    // VersionId
                ];
            }
        }

        $result = [
            'DeleteResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
            ],
        ];

        if (count($deleted) > 0) {
            $result['DeleteResult']['#Deleted'] = $deleted;
        }
        if (count($errors) > 0) {
            $result['DeleteResult']['#Error'] = $errors;
        }

        return $result;
    }
}
