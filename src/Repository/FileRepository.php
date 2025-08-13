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
        $escapedPrefix = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $prefix);

        $qb =  $this->createQueryBuilder('f')
            ->andWhere('f.bucket = :bucket')
            ->andWhere('f.name LIKE :prefix')
            ->setParameter('bucket', $bucket)
            ->setParameter('prefix', $escapedPrefix.'%')
            ->orderBy('f.name', 'ASC');

        if ($marker) {
            if ($markerType === 2) {
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
}
