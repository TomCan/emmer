<?php

namespace App\Tests\Service;

use App\DataFixtures\BaseTestFixture;
use App\DataFixtures\LifecycleTestFixture;
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

    public function testAbortIncompleteMpu(): void
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

        // find mpus older than 11 days (should be none)
        $parsedRule->setAbortIncompleteMultipartUploadDays(11);
        // should not have deleted any mpu
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $this->assertCount(0, iterator_to_array($files, false));

        // keep only 6 days, should deleted 12 keeping 24
        $parsedRule->setAbortIncompleteMultipartUploadDays(6);
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $files = iterator_to_array($files, false);
        $this->assertCount(12, $files);

        // actually invoke method
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // should not result anymore in expired mpus
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $files = iterator_to_array($files, false);
        $this->assertCount(0, $files);
        // check remaining mpus
        $files = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, '');
        $files = iterator_to_array($files, false);
        $this->assertCount(24, $files);

        // test with prefix, keeping only 1 day, should match 6
        $parsedRule->setAbortIncompleteMultipartUploadDays(1);
        $parsedRule->setFilterPrefix('folder1/');
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $files = iterator_to_array($files, false);
        $this->assertCount(6, $files);

        // actually invoke method
        $method->invokeArgs($this->lifecycleService, [$bucket, $parsedRule]);
        // should not result anymore in expired mpus
        $files = $this->fileRepository->findByLifecycleRuleExpiredMpu($bucket, $parsedRule);
        $files = iterator_to_array($files, false);
        $this->assertCount(0, $files);
        // check remaining mpus under prefix (should be 0 as all deleted)
        $files = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, 'folder1/');
        $files = iterator_to_array($files, false);
        $this->assertCount(0, $files);
        // check remaining mpus without prefix (should be 9)
        $files = $this->fileRepository->findMpuPagedByBucketAndPrefix($bucket, '');
        $files = iterator_to_array($files, false);
        $this->assertCount(18, $files);
    }
}
