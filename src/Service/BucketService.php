<?php

namespace App\Service;

use App\Domain\List\BucketList;
use App\Domain\List\ObjectList;
use App\Domain\List\ObjectpartList;
use App\Entity\Bucket;
use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\Policy;
use App\Entity\User;
use App\Exception\Bucket\BucketExistsException;
use App\Exception\Bucket\BucketNotEmptyException;
use App\Exception\Bucket\InvalidBucketNameException;
use App\Exception\Bucket\InvalidVersioningConfigException;
use App\Exception\EmmerRuntimeException;
use App\Exception\Object\InvalidManifestException;
use App\Repository\BucketRepository;
use App\Repository\FilepartRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class BucketService
{
    public function __construct(
        private BucketRepository $bucketRepository,
        private FileRepository $fileRepository,
        private FilepartRepository $filepartRepository,
        private EntityManagerInterface $entityManager,
        private FilesystemService $filesystemService,
        private GeneratorService $generatorService,
        private PolicyService $policyService,
        private HashService $hashService,
        private AuthorizationService $authorizationService,
        private bool $mergeMultipartUploads,
    ) {
    }

    public function isValidBucketName(string $name): bool
    {
        /*
         * Can't start with xn--
         * Can't contain 2 consecutive dots
         * Must start and end with a lowercase letter or digit
         * Must be at least minimum 3 and maximum 63 characters lowercase letters, digits, dots or hyphens
         *  (so 1-61 characters + 1 start and 1 end character)
         */
        return preg_match('/^(?!xn--)(?!.*\.\.)[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $name);
    }

    public function getBucket(string $name): ?Bucket
    {
        return $this->bucketRepository->findOneBy(['name' => $name]);
    }

    /**
     * @return array<Bucket>
     */
    public function getBuckets(): array
    {
        return $this->bucketRepository->findAll();
    }

    public function createBucket(string $name, User $user, string $description = '', string $path = '', bool $addDefaultPolicies = true, bool $flush = true): Bucket
    {
        if (!$this->isValidBucketName($name)) {
            throw new InvalidBucketNameException('Bucket name is invalid');
        }

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

        $this->filesystemService->createBucketPath($bucket);

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

        $this->filesystemService->deleteBucketPath($bucket);

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

    public function getAbsolutePartPath(Filepart $filepart): string
    {
        return $this->filesystemService->getBucketPath($filepart->getFile()->getBucket()).DIRECTORY_SEPARATOR.$filepart->getPath();
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

    /**
     * @param File[] $iterator
     */
    private function createObjectListFromIterator(mixed $iterator, string $prefix, string $delimiter, int $maxKeys): ObjectList
    {
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
                    $commonPrefix = $prefix.substr($fileName, 0, strpos($fileName, $delimiter) + strlen($delimiter));
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

    /**
     * @param Filepart[] $iterator
     */
    private function createObjectpartListFromIterator(mixed $iterator, int $maxKeys): ObjectpartList
    {
        // delimiter, return files without delimiter, and group those with delimiter
        $objectpartList = new ObjectpartList();
        foreach ($iterator as $filepart) {
            // check if we have reached max-keys
            if (count($objectpartList->getFileparts()) == $maxKeys) {
                $objectpartList->setTruncated(true);
                $objectpartList->setNextMarker($filepart->getPartNumber());

                return $objectpartList;
            }

            $objectpartList->addFilepart($filepart);
        }

        return $objectpartList;
    }

    public function listFiles(Bucket $bucket, string $prefix = '', string $delimiter = '', string $marker = '', int $markerType = 1, int $maxKeys = 100): ObjectList
    {
        if ($delimiter) {
            // we need to group files, request
            $iterator = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, $prefix, $marker, 0, $markerType);
        } else {
            $iterator = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, $prefix, $marker, $maxKeys + 1, $markerType);
        }

        return $this->createObjectListFromIterator($iterator, $prefix, $delimiter, $maxKeys);
    }

    public function listFileVersions(Bucket $bucket, string $prefix = '', string $delimiter = '', string $keyMarker = '', string $versionMarker = '', int $maxKeys = 100): ObjectList
    {
        if ($delimiter) {
            // we need to group files
            $iterator = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, $prefix, $keyMarker, $versionMarker, 0);
        } else {
            $iterator = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, $prefix, $keyMarker, $versionMarker, $maxKeys + 1);
        }

        return $this->createObjectListFromIterator($iterator, $prefix, $delimiter, $maxKeys);
    }

    public function listMultipartUploads(Bucket $bucket, string $prefix = '', string $delimiter = '', string $keyMarker = '', string $uploadIdMarker = '', int $maxKeys = 100): ObjectList
    {
        if ($delimiter) {
            // we need to group files
            $iterator = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, $prefix, $keyMarker, $uploadIdMarker, 0);
        } else {
            $iterator = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, $prefix, $keyMarker, $uploadIdMarker, $maxKeys + 1);
        }

        return $this->createObjectListFromIterator($iterator, $prefix, $delimiter, $maxKeys);
    }

    public function listMpuParts(File $file, int $partNumberMarker = 0, int $maxParts = 100): ObjectpartList
    {
        $iterator = $this->filepartRepository->findPagedByFile($file, $partNumberMarker, $maxParts + 1);

        return $this->createObjectpartListFromIterator($iterator, $maxParts);
    }

    public function getFile(Bucket $bucket, string $name, ?string $versionId = ''): ?File
    {
        if ('' === $versionId) {
            // return current version
            return $this->fileRepository->findOneBy(['bucket' => $bucket, 'name' => $name, 'currentVersion' => true, 'multipartUploadId' => null], ['mtime' => 'DESC', 'id' => 'DESC']);
        } else {
            // return specific version
            return $this->fileRepository->findOneBy(['bucket' => $bucket, 'name' => $name, 'version' => $versionId, 'multipartUploadId' => null]);
        }
    }

    public function getFileMpu(Bucket $bucket, string $name, string $uploadId): ?File
    {
        return $this->fileRepository->findOneBy(['bucket' => $bucket, 'name' => $name, 'multipartUploadId' => $uploadId]);
    }

    public function saveFile(File $file, bool $flush = true): void
    {
        $this->entityManager->persist($file);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteFile(File $file, bool $deleteFromStorage = true, bool $flush = true): ?File
    {
        if ($file->getBucket()->isVersioned()) {
            // versioned bucket, insert delete marker instead of deleting file
            $deleteMarker = $this->createFile($file->getBucket(), $file->getName(), $file->getContentType());
            $deleteMarker->setDeleteMarker(true);
            $this->entityManager->persist($deleteMarker);

            // make deletemarker active version
            $this->makeVersionActive($deleteMarker, $file, false);

            if ($flush) {
                $this->entityManager->flush();
            }

            return $deleteMarker;
        } else {
            // non-versioned bucket, actually delete this file "version"
            $this->deleteFileVersion($file, $deleteFromStorage, $flush);

            return $file;
        }
    }

    public function deleteFileVersion(File $file, bool $deleteFromStorage = true, bool $flush = true): void
    {
        // delete File and Fileparts
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

    /**
     * Make a new version of a file active.
     *
     * If the bucket is not versioned, the old file will be deleted.
     * If the bucket is versioned, the old file will be kept as an inactive version.
     */
    public function makeVersionActive(File $newFile, ?File $oldFile, bool $flush = true): void
    {
        $newFile->setCurrentVersion(true);
        $this->entityManager->persist($newFile);

        if ($oldFile) {
            if ($oldFile->getBucket()->isVersioned()) {
                // versioned bucket, make old version inactive
                $oldFile->setCurrentVersion(false);
                $this->entityManager->persist($oldFile);
            } else {
                // unversioned bucket, delete old version
                $this->deleteFileVersion($oldFile, true, false);
            }
        }

        if ($flush) {
            $this->entityManager->flush();
        }
    }

    public function deleteFilepart(Filepart $filepart, bool $deleteFromStorage = true, bool $flush = true): void
    {
        $this->entityManager->remove($filepart);

        if ($flush) {
            $this->entityManager->flush();
        }

        if ($deleteFromStorage) {
            $this->filesystemService->deleteFilepart($filepart);
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

    public function createFile(Bucket $bucket, string $key, string $contentType = ''): File
    {
        if ($bucket->isVersioned()) {
            $version = $this->generatorService->generateId(32);
        } else {
            $version = null;
        }

        return new File($bucket, $key, $version, $contentType);
    }

    /**
     * @param resource $inputResource
     */
    public function createFileAndFilepartFromResource(Bucket $bucket, string $key, string $contentType, mixed $inputResource): File
    {
        $file = $this->createFile($bucket, $key, $contentType);
        $filepart = $this->createFilePartFromResource($file, 1, $inputResource);

        $file->setMtime($filepart->getMtime());
        $file->setSize($filepart->getSize());
        $file->setEtag($filepart->getEtag());

        return $file;
    }

    /**
     * @param resource|Request|string $inputResource
     */
    public function createFilePartFromResource(File $file, int $partNumber, mixed $inputResource): Filepart
    {
        // multipart upload exists
        $filePart = new Filepart($file, $partNumber, $this->generatorService->generateId(32), $this->getUnusedPath($file->getBucket()));
        $file->addFilepart($filePart);

        $bytesWritten = $this->filesystemService->writeFilepart($filePart, $inputResource);

        $file->setMtime(new \DateTime());
        $filePart->setMtime($file->getMtime());
        $filePart->setSize($bytesWritten);
        $filePart->setEtag('"'.$this->hashService->hashFilepart($filePart, $this->filesystemService->getBucketPath($file->getBucket())).'"');

        return $filePart;
    }

    public function createMultipartUpload(Bucket $bucket, string $key, string $contentType = ''): File
    {
        $id = $this->generatorService->generateId(64);
        $file = new File($bucket, $key, null, $contentType);
        $file->setMultipartUploadId($id);
        $this->saveFile($file);

        return $file;
    }

    public function completeMultipartUpload(File $file, \SimpleXMLElement $manifest): File
    {
        $bucket = $file->getBucket();

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
            if ('' !== $etag && $fileParts[$partNumber - 1]->getEtag() !== $etag) {
                throw new InvalidManifestException('Part '.$partNumber.' ETag does not match', 3);
            }

            $parts[] = $fileParts[$partNumber - 1];
        }

        // we have all parts in the order we need them. We can proceed.

        // Start transaction as we need to flush multiple times due to UnitOfWork order
        $this->entityManager->beginTransaction();

        // always create a new File object
        $targetFile = $this->createFile($file->getBucket(), $file->getName(), $file->getContentType());
        $this->saveFile($targetFile, true);

        $bucketPath = $this->filesystemService->getBucketPath($bucket);
        if ($this->mergeMultipartUploads) {
            // create receiving Filepart
            $targetPart = new Filepart($targetFile, 1, $this->generatorService->generateId(32), $this->getUnusedPath($bucket));
            $targetFile->addFilepart($targetPart);

            // Merge all parts into one part
            $bytesWritten = $this->filesystemService->mergeFileparts($file, $targetPart);

            // calculate md5 hash of merged file
            $targetPart->setEtag('"'.$this->hashService->hashFilepart($targetPart, $bucketPath).'"');
            $targetPart->setSize($bytesWritten);
            $targetPart->setMtime(new \DateTime());

            // use same info for file, no need to recalculate
            $targetPart->setSize($bytesWritten);
            $targetFile->setEtag($targetPart->getEtag());
            $targetFile->setMtime($targetPart->getMtime());
        } else {
            // keep separate parts. Link them to the target file.
            foreach ($parts as $part) {
                $file->removeFilepart($part);
                $targetFile->addFilepart($part);
            }

            // calculate md5 hash of the File across all parts
            $targetFile->setEtag('"'.$this->hashService->hashFileFilepartHashes($targetFile).'"');
            $targetFile->setSize($this->calculateFileSize($targetFile));
            $targetFile->setMtime(new \DateTime());

            // flush state of the old file without parts and the new file with the parts
            $this->entityManager->flush();
        }

        // Now delete the old MPU file and then save the new one. Flush to prevent duplicate keys
        $this->deleteFileVersion($file, true, true);
        $this->saveFileAndParts($targetFile, true);

        // finally, activate the new file and deactive the old one (if any)
        $oldFile = $this->getFile($bucket, $file->getName());
        $this->makeVersionActive($targetFile, $oldFile, true);

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

            $key = (string) $object->Key;
            if ('' == ($object->VersionId ?? '')) {
                $versionId = '';
                $requiredAction = 's3:DeleteObject';
            } elseif ('null' == (string) $object->VersionId) {
                $versionId = null;
                $requiredAction = 's3:DeleteObjectVersion';
            } else {
                $versionId = (string) $object->VersionId;
                $requiredAction = 's3:DeleteObjectVersion';
            }

            // check if user has permissions to delete this object
            try {
                $this->authorizationService->requireAll(
                    $user,
                    [['action' => $requiredAction, 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key]],
                    $bucket
                );
            } catch (AccessDeniedException $e) {
                $errors[] = [
                    'Key' => $key,
                    'Code' => 'AccessDenied',
                    'Message' => 'Access Denied.',
                    'VersionId' => $versionId ?? 'null',
                ];
                continue;
            }

            $file = $this->getFile($bucket, (string) $object->Key, $versionId);
            if (!$file) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'NoSuchKey',
                    'Message' => 'The specified key does not exist.',
                    'VersionId' => $versionId ?? 'null',
                ];
                continue;
            }

            // check if ETag is present and matches
            if ($object->ETag && $file->getEtag() !== (string) $object->ETag) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'ETagMismatch',
                    'Message' => 'ETag Mismatch',
                    'VersionId' => $versionId ?? 'null',
                ];
                continue;
            }

            // check if LastModifiedTime is present
            if ($object->LastModifiedTime && $file->getMtime() !== new \DateTime((string) $object->LastModifiedTime)) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'LastModifiedTimeMismatch',
                    'Message' => 'LastModifiedTime Mismatch',
                    'VersionId' => $versionId ?? 'null',
                ];
                continue;
            }

            if ($object->Size && $file->getSize() !== (int) $object->Size) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'SizeMismatch',
                    'Message' => 'Size Mismatch',
                    'VersionId' => $versionId ?? 'null',
                ];
                continue;
            }

            // ready to delete
            try {
                if ('' === $versionId) {
                    $deletedFile = $this->deleteFile($file, true, true);

                    if (!$quiet) {
                        $deleted[] = [
                            'Key' => (string) $object->Key,
                            'DeleteMarker' => $deletedFile->isDeleteMarker() ? 'true' : 'false',
                            'DeleteMarkerVersionId' => $deletedFile->getVersion() ?? 'null',
                            'VersionId' => $file->getVersion() ?? 'null',
                        ];
                    }
                } else {
                    $this->deleteFileVersion($file, true, true);

                    if (!$quiet) {
                        $deleted[] = [
                            'Key' => (string) $object->Key,
                            'DeleteMarker' => $file->isDeleteMarker() ? 'true' : 'false',
                            'VersionId' => $file->getVersion() ?? 'null',
                        ];
                    }
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'FilesystemError',
                    'Message' => 'Filesystem Error',
                    'VersionId' => $file->getVersion() ?? 'null',
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

    public function setBucketVersioning(Bucket $bucket, \SimpleXMLElement $config, bool $flush = false): void
    {
        // must be VersioningConfiguration request
        if ('VersioningConfiguration' !== $config->getName()) {
            throw new InvalidVersioningConfigException('Provided XML is not a valid VersioningConfiguration element', 0);
        }

        if (!$config->Status || ('Enabled' !== (string) $config->Status && 'Suspended' !== (string) $config->Status)) {
            throw new InvalidVersioningConfigException('Invalid Status in VersioningConfiguration element', 1);
        }

        $bucket->setVersioned('Enabled' === (string) $config->Status);
        $this->entityManager->persist($bucket);
        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
