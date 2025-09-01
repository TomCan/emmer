<?php

namespace App\Repository;

use App\Entity\Bucket;
use App\Entity\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $marker = '', int $maxKeys = 100, int $markerType = 1): iterable
    {
        $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);

        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.currentVersion = 1')
            ->andWhere('f.bucket = :bucket')
            ->andWhere('f.name LIKE :prefix')
            ->setParameter('bucket', $bucket)
            ->setParameter('prefix', $escapedPrefix.'%')
            ->orderBy('f.name', 'ASC');

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

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findVersionsPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $keyMarker = '', string $versionMarker = '', int $maxKeys = 100): iterable
    {
        $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);

        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->andWhere('f.name LIKE :prefix')
            ->setParameter('bucket', $bucket)
            ->setParameter('prefix', $escapedPrefix.'%')
            ->orderBy('f.name', 'ASC')
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
        } elseif ($keyMarker) {
            $qb
                ->andWhere('f.name >= :name')
                ->setParameter('name', $keyMarker)
            ;
        }

        if ($maxKeys > 0) {
            $qb->setMaxResults($maxKeys);
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return File[]
     */
    public function findMpuPagedByBucketAndPrefix(Bucket $bucket, string $prefix, string $keyMarker = '', string $uploadIdMarker = '', int $maxKeys = 100): iterable
    {
        $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);

        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->andWhere('f.name LIKE :prefix')
            ->andWhere('f.multipartUploadId IS NOT NULL')
            ->setParameter('bucket', $bucket)
            ->setParameter('prefix', $escapedPrefix.'%')
            ->orderBy('f.name', 'ASC')
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
        } elseif ($keyMarker) {
            $qb
                ->andWhere('f.name >= :name')
                ->setParameter('name', $keyMarker)
            ;
        }

        if ($maxKeys > 0) {
            $qb->setMaxResults($maxKeys);
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }
}
