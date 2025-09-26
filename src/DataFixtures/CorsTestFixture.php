<?php

// src/DataFixtures/UserFixture.php

namespace App\DataFixtures;

use App\Entity\Bucket;
use App\Entity\CorsRule;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CorsTestFixture extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $regularBucket = $this->getReference(BaseTestFixture::REF_REGULAR_BUCKET, Bucket::class);

        /*
         * Create cors rules
         */
        $corsRule = new CorsRule($regularBucket, ['GET', 'PUT', 'POST', 'DELETE'], ['emmer.emr']);
        $manager->persist($corsRule);

        $corsRule = new CorsRule($regularBucket, ['PUT'], ['put.emmer.emr']);
        $manager->persist($corsRule);

        $corsRule = new CorsRule($regularBucket, ['GET'], ['*']);
        $manager->persist($corsRule);

        $corsRule = new CorsRule($regularBucket, ['GET'], ['header.emmer.emr']);
        $corsRule->setAllowedHeaders(['content-type', 'x-emmer']);
        $manager->persist($corsRule);

        $corsRule = new CorsRule($regularBucket, ['PUT'], ['maxage.emmer.emr']);
        $corsRule->setMaxAgeSeconds(3600);
        $manager->persist($corsRule);

        $corsRule = new CorsRule($regularBucket, ['PUT'], ['expose.emmer.emr']);
        $corsRule->setExposeHeaders(['x-emmer']);
        $manager->persist($corsRule);

        $manager->flush();
    }

    /**
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['cors'];
    }
}
