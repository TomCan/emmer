<?php

namespace App\Repository;

use App\Entity\Bucket;
use App\Entity\Filepart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Filepart>
 */
class FilepartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Filepart::class);
    }

    public function findOneByBucketAndPath(Bucket $bucket, string $path): ?Filepart
    {
        return $this->createQueryBuilder('fp')
            ->innerJoin('fp.file', 'f')
            ->andWhere('fp.path = :path')
            ->andWhere('f.bucket = :bucket')
            ->setParameter('path', $path)
            ->setParameter('bucket', $bucket)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
