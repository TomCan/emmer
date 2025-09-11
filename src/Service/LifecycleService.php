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

    public function run(Bucket $bucket, ?int $mpu, ?int $ncv, mixed $io): void
    {
        if (null !== $mpu) {
            $expireDate = new \DateTime($mpu.' hours ago');
            if ($io instanceof OutputInterface) {
                $io->writeln('Deleting multi-part uploads older than '.$expireDate->format('Y-m-d H:i:s'));
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

        if (null !== $ncv) {
            $expireDate = new \DateTime($ncv.' hours ago');
            if ($io instanceof OutputInterface) {
                $io->writeln('Deleting non-current version older than '.$expireDate->format('Y-m-d H:i:s'));
            }
            $keyMarker = '';
            $versionMarker = '';
            do {
                $objects = $this->bucketService->listFileVersions($bucket, '', '', $keyMarker, $versionMarker, 1000);
                foreach ($objects->getFiles() as $file) {
                    if (!$file->isCurrentVersion() && $file->getMtime() < $expireDate) {
                        // delete file version
                        try {
                            $this->bucketService->deleteFileVersion($file, true, true);
                            if ($io instanceof OutputInterface) {
                                $io->writeln('Deleted '.$file->getVersion().' '.$file->getName());
                            }
                        } catch (\Exception $e) {
                            if ($io instanceof OutputInterface) {
                                $io->writeln('Unable to delete '.$file->getVersion().' '.$file->getName().': '.$e->getMessage());
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
}
