<?php

namespace App\Repository;

use App\Domain\Lifecycle\ParsedLifecycleRule;
use App\Entity\Bucket;
use App\Entity\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<File>
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * @return File[]
     */
    public function findObjectsPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $marker = '', int $maxKeys = 1000, int $markerType = 1): iterable
    {
        $qb = $this->getPagedQbByBucketAndPrefix($bucket, $prefix, $markerType, $marker, $maxKeys);

        $qb
            ->andWhere('f.multipartUploadId IS NULL')
            ->andWhere('f.currentVersion = 1')
        ;

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findVersionsPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $keyMarker = '', string $versionMarker = '', int $maxKeys = 1000): iterable
    {
        $qb = $this->getPagedQbByBucketAndPrefix($bucket, $prefix, 1, $keyMarker, $maxKeys);

        $qb
            ->andWhere('f.multipartUploadId IS NULL')
            ->addOrderBy('f.id', 'DESC')
        ;

        if ($keyMarker && $versionMarker) {
            $expr = $qb->expr();
            $qb
                ->andWhere(
                    $expr->orX(
                        $expr->andX(
                            $expr->eq('f.name', ':name'),
                            $expr->gte('f.version', ':version')
                        ),
                        $expr->gt('f.name', ':name')
                    )
                )
                ->setParameter('name', $keyMarker)
                ->setParameter('version', $versionMarker)
            ;
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findMpuPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $keyMarker = '', string $uploadIdMarker = '', int $maxKeys = 1000): iterable
    {
        $qb = $this->getPagedQbByBucketAndPrefix($bucket, $prefix, 1, $keyMarker, $maxKeys);

        $qb
            ->andWhere('f.multipartUploadId IS NOT NULL')
            ->addOrderBy('f.id', 'ASC')
        ;

        if ($keyMarker && $uploadIdMarker) {
            $expr = $qb->expr();
            $qb
                ->andWhere(
                    $expr->orX(
                        $expr->andX(
                            $expr->eq('f.name', ':name'),
                            $expr->gte('f.multipartUploadId', ':uploadId')
                        ),
                        $expr->gt('f.name', ':name')
                    )
                )
                ->setParameter('name', $keyMarker)
                ->setParameter('uploadId', $uploadIdMarker)
            ;
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    private function getPagedQbByBucketAndPrefix(Bucket $bucket, string $prefix, int $markerType = 1, string $marker = '', int $maxKeys = 1000): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->setParameter('bucket', $bucket)
            ->orderBy('f.name', 'ASC');

        if ($prefix) {
            $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);
            $qb
                ->andWhere('f.name LIKE :prefix')
                ->setParameter('prefix', $escapedPrefix.'%')
            ;
        }

        if ($marker) {
            if (2 === $markerType) {
                // treat as start-after
                $qb
                    ->andWhere('f.name > :marker')
                    ->setParameter('marker', $marker);
            } else {
                // treat as market or continuation-token
                $qb
                    ->andWhere('f.name >= :marker')
                    ->setParameter('marker', $marker);
            }
        }

        if ($maxKeys > 0) {
            $qb->setMaxResults($maxKeys);
        }

        return $qb;
    }

    private function applyLifecycleFilter(QueryBuilder $qb, ParsedLifecycleRule $rule): void
    {
        if (null != $rule->getFilterPrefix() || null != $rule->getFilterAndPrefix()) {
            $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $rule->getFilterPrefix() ?? $rule->getFilterAndPrefix());
            $qb->andWhere('f.name LIKE :prefix')
                ->setParameter('prefix', $escapedPrefix.'%');
        }
        if (null != $rule->getFilterSizeGreaterThan() || null != $rule->getFilterAndSizeGreaterThan()) {
            $qb->andWhere('f.size > :sizegt')
                ->setParameter('sizegt', $rule->getFilterSizeGreaterThan() ?? $rule->getFilterAndSizeGreaterThan());
        }
        if (null != $rule->getFilterSizeLessThan() || null != $rule->getFilterAndSizeLessThan()) {
            $qb->andWhere('f.size < :sizelt')
                ->setParameter('sizelt', $rule->getFilterSizeLessThan() ?? $rule->getFilterAndSizeLessThan());
        }
        if (null != $rule->getFilterTag() || null != $rule->getFilterAndTags()) {
            // tags not supported
            $tags = $rule->getFilterTag() ? [$rule->getFilterTag()] : $rule->getFilterAndTags();
        }
    }

    /**
     * @return File[]
     */
    public function findByLifecycleRuleExpiredMpu(Bucket $bucket, ParsedLifecycleRule $rule): iterable
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->setParameter('bucket', $bucket)
        ;

        if ($rule->hasFilter()) {
            $this->applyLifecycleFilter($qb, $rule);
        }

        if (null != $rule->getAbortIncompleteMultipartUploadDays()) {
            $qb
                ->andWhere('f.multipartUploadId IS NOT NULL')
                ->andWhere('f.ctime < :ctime')
                ->setParameter('ctime', (new \DateTime('now', new \DateTimeZone('UTC')))->sub(new \DateInterval('P'.$rule->getAbortIncompleteMultipartUploadDays().'D')));
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findByLifecycleRuleExpiredCurrentVersions(Bucket $bucket, ParsedLifecycleRule $rule): iterable
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->setParameter('bucket', $bucket)
        ;

        if ($rule->hasFilter()) {
            $this->applyLifecycleFilter($qb, $rule);
        }

        /*
         * Current versions expiration
         */
        $qb->andWhere('f.multipartUploadId IS NULL')
            ->andWhere('f.currentVersion = 1')
            ->andWhere('f.deleteMarker = 0');

        if (null != $rule->getExpirationDate()) {
            if ($rule->getExpirationDate() > new \DateTime()) {
                // not yet expired, insert false condition to avoid deletion
                $qb->andWhere('f.id = 0');
            }
        } elseif (null != $rule->getExpirationDays()) {
            $qb->andWhere('f.mtime < :expmtime')
                ->setParameter('expmtime', (new \DateTime())->sub(new \DateInterval('P'.$rule->getExpirationDays().'D')));
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findByLifecycleRuleExpiredNoncurrentVersions(Bucket $bucket, ParsedLifecycleRule $rule): iterable
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->setParameter('bucket', $bucket)
        ;

        if ($rule->hasFilter()) {
            $this->applyLifecycleFilter($qb, $rule);
        }

        /*
         * Noncurrent versions expiration
         */
        if (null != $rule->getNoncurrentVersionExpirationDays()) {
            $qb->andWhere('f.multipartUploadId IS NULL')
                ->andWhere('f.currentVersion = 0')
                ->andWhere('f.nctime < :nvcexpmtime')
                ->setParameter('nvcexpmtime', (new \DateTime())->sub(new \DateInterval('P'.$rule->getNoncurrentVersionExpirationDays().'D')));

            if (null != $rule->getNoncurrentVersionNewerVersions()) {
                // keep at least N noncurrent versions
                $qb->andWhere('f.newerNoncurrentVersions >= :nvcnewerversions')
                    ->setParameter('nvcnewerversions', $rule->getNoncurrentVersionNewerVersions());
            }
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }
}
