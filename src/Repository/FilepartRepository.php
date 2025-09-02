<?php

namespace App\Repository;

use App\Entity\Bucket;
use App\Entity\File;
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

    /**
     * @return Filepart[]
     */
    public function findPagedByFile(File $file, int $marker = 0, int $maxParts = 1000): iterable
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.file = :file')
            ->setParameter('file', $file)
            ->orderBy('p.partNumber', 'ASC');

        if ($marker) {
            // treat as start-after
            $qb
                ->andWhere('p.partNumber > :marker')
                ->setParameter('marker', $marker);
        }

        if ($maxParts > 0) {
            $qb->setMaxResults($maxParts);
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }
}
