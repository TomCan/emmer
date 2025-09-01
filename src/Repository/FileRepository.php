<?php

namespace App\Repository;

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
}
