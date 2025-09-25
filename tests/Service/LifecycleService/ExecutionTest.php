<?php

namespace App\Tests\Service;

use App\DataFixtures\BaseTestFixture;
use App\DataFixtures\LifecycleTestFixture;
use App\DataFixtures\LifecycleVersionedTestFixture;
use App\Domain\Lifecycle\ParsedLifecycleRule;
use App\Repository\FileRepository;
use App\Service\BucketService;
use App\Service\LifecycleService;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ExecutionTest extends KernelTestCase
{
    private LifecycleService $lifecycleService;
    private EntityManagerInterface $entityManager;
    private FileRepository $fileRepository;
    private BucketService $bucketService;

    protected function setUp(): void
    {
        // Boot the Symfony kernel and get the container
        self::bootKernel();
        $container = static::getContainer();
        // Get services
        $this->lifecycleService = $container->get(LifecycleService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->fileRepository = $container->get(FileRepository::class);
        $this->bucketService = $container->get(BucketService::class);
    }

    protected function tearDown(): void
    {
        // Rollback transaction to clean up database changes
        $this->entityManager->close();

        parent::tearDown();
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new BaseTestFixture());
        $loader->addFixture(new LifecycleTestFixture($this->bucketService));
        $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
        $executor->execute($loader->getFixtures());
    }

    private function loadVersionedFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new BaseTestFixture());
        $loader->addFixture(new LifecycleVersionedTestFixture($this->bucketService));
        $executor = new ORMExecutor($this->entityManager, new ORMPurger($this->entityManager));
        $executor->execute($loader->getFixtures());
    }

    public function testFilter(): void
    {
        $this->loadFixtures();

        // get bucket
        $bucket = $this->bucketService->getBucket('regular-bucket');

        // prep rule
        $parsedRule = new ParsedLifecycleRule();
        $parsedRule->setStatus('Enabled');

        // will match every multi-part upload
        $parsedRule->setAbortIncompleteMultipartUploadDays(1);
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(36, iterator_to_array($files));

        // test with prefix (matches 1/4th of the files = 9
        $parsedRule->setFilterPrefix('folder1/');
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(9, iterator_to_array($files));

        // test with minimum size (matches 2/3th of the files = 24)
        $parsedRule->setFilterPrefix(null);
        $parsedRule->setFilterSizeGreaterThan(99);
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(24, iterator_to_array($files));

        // test with maximum size (matches 2/3th of the files = 24)
        $parsedRule->setFilterSizeGreaterThan(null);
        $parsedRule->setFilterSizeLessThan(101);
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(24, iterator_to_array($files));

        // test with And, both minimum as maximum size matches 1/3 = 12)
        $parsedRule->setFilterSizeLessThan(null);
        $parsedRule->setFilterAndSizeGreaterThan(99);
        $parsedRule->setFilterAndSizeLessThan(101);
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(12, iterator_to_array($files));

        // test with And, both minimum as maximum size, and prefix matches 3
        $parsedRule->setFilterAndSizeGreaterThan(99);
        $parsedRule->setFilterAndSizeLessThan(101);
        $parsedRule->setFilterAndPrefix('folder1/');
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(3, iterator_to_array($files));
    }

    public function testAbortIncompleteMpuRegular(): void
    {
        $reflection = new \ReflectionClass($this->lifecycleService);
        $method = $reflection->getMethod('processBucketLifecycleRule');
        $method->setAccessible(true);

        $this->loadFixtures();

        // get bucket
        $bucket = $this->bucketService->getBucket('regular-bucket');

        // prep rule
        $parsedRule = new ParsedLifecycleRule();
        $parsedRule->setStatus('Enabled');

        // find mpus older than 11 days (matches 0 mpus)
        $parsedRule->setAbortIncompleteMultipartUploadDays(11);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // check remaining mpus
        $files = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, '');
        $this->assertCount(36, iterator_to_array($files));

        // find mpus older than 4 days (matches 24 mpus)
        $parsedRule->setAbortIncompleteMultipartUploadDays(4);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // check remaining mpus
        $files = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, '');
        $this->assertCount(12, iterator_to_array($files));
    }

    public function testExpireCurrentRegular(): void
    {
        $reflection = new \ReflectionClass($this->lifecycleService);
        $method = $reflection->getMethod('processBucketLifecycleRule');
        $method->setAccessible(true);

        $this->loadFixtures();

        // get bucket
        $bucket = $this->bucketService->getBucket('regular-bucket');

        // prep rule
        $parsedRule = new ParsedLifecycleRule();
        $parsedRule->setStatus('Enabled');

        // expire current version older than 7 days (matches 12 files)
        $parsedRule->setExpirationDays(7);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // check remaining mpus
        $files = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, '');
        $this->assertCount(24, iterator_to_array($files));

        // expire date in future, should not match any
        $parsedRule->setExpirationDays(null);
        $parsedRule->setExpirationDate(new \DateTime('+1 year'));
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // check remaining mpus
        $files = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, '');
        $this->assertCount(24, iterator_to_array($files));

        // expire date in the past, should match all
        $parsedRule->setExpirationDate(new \DateTime('-1 year'));
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // check remaining mpus
        $files = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, '');
        $this->assertCount(0, iterator_to_array($files));
    }

    public function testExpireCurrentVersioned(): void
    {
        $reflection = new \ReflectionClass($this->lifecycleService);
        $method = $reflection->getMethod('processBucketLifecycleRule');
        $method->setAccessible(true);

        $this->loadFixtures();

        // get bucket
        $bucket = $this->bucketService->getBucket('versioned-bucket');

        // prep rule
        $parsedRule = new ParsedLifecycleRule();
        $parsedRule->setStatus('Enabled');

        // expire current version older than 7 days (matches 12 files)
        $parsedRule->setExpirationDays(7);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);

        // check remaining files
        $files = $this->fileRepository->findObjectsPagedByBucketAndPrefix($bucket, '');
        // still matches 36, as deleted files have been replaced with delete markers
        $this->assertCount(36, iterator_to_array($files));

        // retrieve one of these files and verify it's a delete marker
        $file = $this->bucketService->getFile($bucket, 'folder1/file_10_10');
        $this->assertTrue($file->isDeleteMarker());

        // get all versions of the file
        $versions = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, 'folder1/');
        $count = 0;
        foreach ($versions as $version) {
            if ('folder1/file_10_10' == $version->getName()) {
                ++$count;
            }
        }
        // should have 2 versions (the original and the delete marker)
        $this->assertEquals(2, $count);

        // file still refers to 'folder1/file_10_10', change mtime and re-run
        $file->setMtime(new \DateTime('-1 year'));
        $this->entityManager->persist($file);
        $this->entityManager->flush();
        // re-run
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);

        // get all versions of the file
        $versions = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, 'folder1/');
        $count = 0;
        foreach ($versions as $version) {
            if ('folder1/file_10_10' == $version->getName()) {
                ++$count;
            }
        }
        // should still have 2 versions as expired delete markers don't create new delete markers
        $this->assertEquals(2, $count);
    }

    public function testExpireNoncurrentVersioned(): void
    {
        $reflection = new \ReflectionClass($this->lifecycleService);
        $method = $reflection->getMethod('processBucketLifecycleRule');
        $method->setAccessible(true);

        $this->loadVersionedFixtures();

        // get bucket
        $bucket = $this->bucketService->getBucket('versioned-bucket');

        // prep rule
        $parsedRule = new ParsedLifecycleRule();
        $parsedRule->setStatus('Enabled');

        // remove noncurrent version older than 7 days (matches 2 versions)
        $parsedRule->setNoncurrentVersionExpirationDays(7);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // get all versions of the file
        $versions = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, '');
        $count = 0;
        foreach ($versions as $version) {
            if ('versioned-file' == $version->getName()) {
                ++$count;
            }
        }
        // should have 7 versions
        $this->assertEquals(8, $count);

        // remove noncurrent version older than 2 days (matches 5 versions, but can only delte those with at least 3 newer non-current versions)
        $parsedRule->setNoncurrentVersionExpirationDays(2);
        $parsedRule->setNoncurrentVersionNewerVersions(3);
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // get all versions of the file
        $versions = $this->fileRepository->findVersionsPagedByBucketAndPrefix($bucket, '');
        $count = 0;
        foreach ($versions as $version) {
            if ('versioned-file' == $version->getName()) {
                ++$count;
            }
        }
        // should have 4 version (current + 3 noncurrent)
        $this->assertEquals(4, $count);
    }

}
