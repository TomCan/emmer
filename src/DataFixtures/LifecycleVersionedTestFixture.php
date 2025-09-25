<?php

// src/DataFixtures/UserFixture.php

namespace App\DataFixtures;

use App\Entity\Bucket;
use App\Service\BucketService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class LifecycleVersionedTestFixture extends Fixture
{
    public function __construct(
        private BucketService $bucketService,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $versionedBucket = $this->getReference(BaseTestFixture::REF_VERSIONED_BUCKET, Bucket::class);

        /*
         * Create files with various prefixes, sizes, creation dates and mpu
         */

        $oldFile = null;
        for ($i = 0; $i < 10; ++$i) {
            $newFile = $this->bucketService->createFile($versionedBucket, 'versioned-file');
            $newFile->setCtime(new \DateTime('-'.(10 - $i).' days'));
            $newFile->setMtime($newFile->getCtime());
            $newFile->setSize(1234);
            $this->bucketService->makeVersionActive($newFile, $oldFile, true);
            if ($oldFile) {
                $oldFile->setNctime($newFile->getCtime());
                $this->bucketService->saveFile($oldFile);
            }
            $oldFile = $newFile;
        }
    }

    /**
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['lifecycle-versioned'];
    }
}
