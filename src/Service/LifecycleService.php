<?php

namespace App\Service;

use App\Entity\Bucket;
use Symfony\Component\Console\Output\OutputInterface;

class LifecycleService
{
    public function __construct(
        private BucketService $bucketService,
    ) {
    }

    public function run(Bucket $bucket, int $mpuHours, int $ncvHours, int $cvHours, mixed $io): void
    {
        if ($mpuHours >= 0) {
            $this->deleteMultipartUploads($bucket, $mpuHours, $io);
        }

        if ($ncvHours >= 0) {
            $this->deleteNonCurrentVersions($bucket, $ncvHours, $io);
        }

        if ($cvHours >= 0) {
            $this->deleteCurrentVersions($bucket, $cvHours, $io);
        }
    }

    public function deleteMultipartUploads(Bucket $bucket, int $mpuHours, mixed $io): void
    {
        $expireDate = new \DateTime($mpuHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting multi-part uploads older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $uploadIdMarker = '';
        do {
            $objects = $this->bucketService->listMultipartUploads($bucket, '', '', $keyMarker, $uploadIdMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if ($file->getMtime() < $expireDate) {
                    // clean up multipart upload
                    try {
                        $this->bucketService->deleteFileVersion($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.$file->getMultipartUploadId().' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.$file->getMultipartUploadId().' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $uploadIdMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $uploadIdMarker = '';
            }
        } while ('' != $keyMarker);
    }

    public function deleteCurrentVersions(Bucket $bucket, int $cvHours, mixed $io): void
    {
        $expireDate = new \DateTime($cvHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting current versions older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $versionMarker = '';
        do {
            $objects = $this->bucketService->listFileVersions($bucket, '', '', $keyMarker, $versionMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if ($file->isCurrentVersion() && $file->getMtime() < $expireDate) {
                    // delete file
                    try {
                        $this->bucketService->deleteFile($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.($file->getVersion() ?? 'NULL').' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.($file->getVersion() ?? 'NULL').' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $versionMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $versionMarker = '';
            }
        } while ('' != $keyMarker);
    }

    public function deleteNonCurrentVersions(Bucket $bucket, int $ncvHours, mixed $io): void
    {
        $expireDate = new \DateTime($ncvHours.' hours ago');
        if ($io instanceof OutputInterface) {
            $io->writeln('Deleting non-current versions older than '.$expireDate->format('Y-m-d H:i:s').' from bucket '.$bucket->getName());
        }
        $keyMarker = '';
        $versionMarker = '';
        do {
            $objects = $this->bucketService->listFileVersions($bucket, '', '', $keyMarker, $versionMarker, 1000);
            foreach ($objects->getFiles() as $file) {
                if (!$file->isCurrentVersion() && $file->getMtime() < $expireDate) {
                    // delete file
                    try {
                        $this->bucketService->deleteFileVersion($file, true, true);
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Deleted '.($file->getVersion() ?? 'NULL').' '.$file->getName());
                        }
                    } catch (\Exception $e) {
                        if ($io instanceof OutputInterface) {
                            $io->writeln('Unable to delete '.($file->getVersion() ?? 'NULL').' '.$file->getName().': '.$e->getMessage());
                        }
                    }
                }
            }
            if ($objects->isTruncated()) {
                $keyMarker = $objects->getNextMarker();
                $versionMarker = $objects->getNextMarker2();
            } else {
                $keyMarker = '';
                $versionMarker = '';
            }
        } while ('' != $keyMarker);
    }
}
