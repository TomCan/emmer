<?php

namespace App\Repository;

use App\Entity\Bucket;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Bucket>
 */
class BucketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Bucket::class);
    }

    /**
     * @return Bucket[]
     */
    public function findPagedByOwnerAndPrefix(User $owner, string $prefix, string $marker = '', int $maxItems = 100): iterable
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('b.name', 'ASC');

        if ('' !== trim($prefix)) {
            $escapedPrefix = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $prefix);
            $qb
                ->andWhere('b.name LIKE :prefix')
                ->setParameter('prefix', $escapedPrefix.'%');
        }

        if ($marker) {
            // treat as marker or continuation-token
            $qb
                ->andWhere('b.name >= :marker')
                ->setParameter('marker', $marker);
        }

        if ($maxItems > 0) {
            $qb->setMaxResults($maxItems);
        }

        return $qb
            ->getQuery()
            ->toIterable();
    }

    /**
     * @return Bucket[]
     */
    public function findWithLifecycleConfiguration(): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.lifecycleConfiguration IS NOT NULL')
            ->getQuery()
            ->toIterable();
    }
}
