<?php

namespace App\Service;

use App\Domain\List\ObjectList;
use App\Entity\Bucket;
use App\Entity\File;
use App\Repository\BucketRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\EntityManagerInterface;

class BucketService
{
    public function __construct(
        private BucketRepository $bucketRepository,
        private FileRepository $fileRepository,
        private EntityManagerInterface $entityManager,
        private GeneratorService $generatorService,
    ) {}

    public function getBucket(string $name): ?Bucket
    {
        return $this->bucketRepository->findOneBy(['name' => $name]);
    }

    public function getUnusedPath(Bucket $bucket): string
    {
        $i = 0;
        $path = $this->generatorService->generateId(32);
        $path = substr($path, 0, 2).DIRECTORY_SEPARATOR.$path;
        while ($this->fileRepository->findOneBy(['bucket' => $bucket, 'path' => $path])) {
            $path = uniqid().uniqid();
            $path = substr($path, 0, 2).DIRECTORY_SEPARATOR.$path;
        }

        return $path;
    }

    public function listFiles(Bucket $bucket, string $prefix, string $delimiter = '', string $marker = '', int $markerType = 1, int $maxKeys = 100): ObjectList
    {
        if ($delimiter) {
            // we need to group files, request
            $iterator = $this->fileRepository->findPagedByBucketAndPrefix($bucket, $prefix, $marker, 0, $markerType);
        } else {
            $iterator = $this->fileRepository->findPagedByBucketAndPrefix($bucket, $prefix, $marker, $maxKeys +1, $markerType);
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
                if ($prefix !== '') {
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
}
