<?php

// src/DataFixtures/UserFixture.php

namespace App\DataFixtures;

use App\Entity\Bucket;
use App\Service\BucketService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LifecycleTestFixture extends Fixture
{
    public function __construct(
        private BucketService $bucketService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $regularBucket = $this->getReference(BaseTestFixture::REF_REGULAR_BUCKET, Bucket::class);
        $versionedBucket = $this->getReference(BaseTestFixture::REF_VERSIONED_BUCKET, Bucket::class);

        /*
         * Create files with various prefixes, sizes, creation dates and mpu
         */
        $fileCount = 0;
        foreach (['', 'folder1/', 'folder2/', 'folder3/'] as $prefix) {
            foreach ([3, 5, 10] as $expire) {
                foreach ([10, 100, 1000] as $size) {
                    foreach ([false, true] as $mpu) {
                        ++$fileCount;
                        $file = $this->bucketService->createFile($regularBucket, $prefix.'file_'.$expire.'_'.$size);
                        $file->setCtime((new \DateTime())->sub(new \DateInterval('P'.$expire.'D')));
                        $file->setMtime($file->getCtime());
                        $file->setSize($size);
                        if ($mpu) {
                            $file->setMultipartUploadId('mpu'.$fileCount);
                        } else {
                            $file->setCurrentVersion(true);
                        }
                        $manager->persist($file);
                    }
                }
            }
        }

        /*
         * Create files with various prefixes, sizes, creation dates, in versioned bucket
         */
        $fileCount = 0;
        foreach (['', 'folder1/', 'folder2/', 'folder3/'] as $prefix) {
            foreach ([3, 5, 10] as $expire) {
                foreach ([10, 100, 1000] as $size) {
                    $file = $this->bucketService->createFile($versionedBucket, $prefix.'file_'.$expire.'_'.$size);
                    $file->setCtime((new \DateTime())->sub(new \DateInterval('P'.$expire.'D')));
                    $file->setMtime($file->getCtime());
                    $file->setSize($size);
                    $file->setCurrentVersion(true);
                    $manager->persist($file);
                }
            }
        }

        $manager->flush();
    }

    /**
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['lifecycle'];
    }
}
