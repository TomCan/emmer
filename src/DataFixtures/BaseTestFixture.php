<?php
// src/DataFixtures/UserFixture.php

namespace App\DataFixtures;

use App\Entity\AccessKey;
use App\Entity\Bucket;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BaseTestFixture extends Fixture
{
    public const REF_ADMIN_USER = 'admin-user';
    public const REF_REGULAR_USER = 'regular-user-';
    public const REF_REGULAR_BUCKET = 'regular-bucket';
    public const REF_VERSIONED_BUCKET = 'versioned-bucket';

    public function load(ObjectManager $manager): void
    {
        // create an admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setPassword('admin');
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);

        $adminKeys = new AccessKey();
        $adminKeys->setUser($admin);
        $adminKeys->setLabel('Admin key');
        $adminKeys->setName('AAAAAAAAAAAAAAAAAAAA');
        $adminKeys->setSecret('QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFB'); // AAAAAAA... 30 characters
        $manager->persist($adminKeys);

        // create an admin user
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword('user');
        $user->setRoles(['ROLE_USER']);
        $manager->persist($user);

        $userKeys = new AccessKey();
        $userKeys->setUser($user);
        $userKeys->setLabel('User key');
        $userKeys->setName('BBBBBBBBBBBBBBBBBBBB');
        $userKeys->setSecret('QkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJCQkJC'); // BBBBBBB... 30 characters
        $manager->persist($userKeys);

        $bucket = new Bucket();
        $bucket->setName('regular-bucket');
        $bucket->setOwner($user);
        $bucket->setDescription('Regular bucket');
        $bucket->setPath('regular-bucket');
        $bucket->setCtime(new \DateTime());
        $bucket->setVersioned(false);
        $manager->persist($bucket);

        $versionedBucket = new Bucket();
        $versionedBucket->setName('versioned-bucket');
        $versionedBucket->setOwner($user);
        $versionedBucket->setDescription('Versioned bucket');
        $versionedBucket->setPath('versioned-bucket');
        $versionedBucket->setCtime(new \DateTime());
        $versionedBucket->setVersioned(true);
        $manager->persist($versionedBucket);

        $manager->flush();

        $this->addReference(self::REF_ADMIN_USER, $admin);
        $this->addReference('admin-keys', $adminKeys);
        $this->addReference(self::REF_REGULAR_USER, $user);
        $this->addReference('user-keys', $userKeys);
        $this->addReference(self::REF_REGULAR_BUCKET, $bucket);
        $this->addReference(self::REF_VERSIONED_BUCKET, $versionedBucket);
    }

    /**
     * @return string[]
     */
    public static function getGroups(): array
    {
        return ['base'];
    }
}
