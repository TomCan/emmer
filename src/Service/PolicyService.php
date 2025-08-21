<?php

namespace App\Service;

use App\Entity\Bucket;
use App\Entity\Policy;
use App\Entity\User;
use App\Repository\PolicyRepository;
use Doctrine\ORM\EntityManagerInterface;

class PolicyService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PolicyRepository $policyRepository,
        private PolicyResolver $policyResolver,
    ) {
    }

    public function getPolicy(int $id): ?Policy
    {
        return $this->policyRepository->find($id);
    }

    public function createPolicy(string $name, string $policyString, ?User $user, ?Bucket $bucket, bool $flush = false): Policy
    {
        // check if valid policy
        $statements = $this->policyResolver->convertPolicies($policyString);
        if (0 == count($statements)) {
            throw new \InvalidArgumentException('Policy must contain at least one valid statement');
        }

        // new object
        $policy = new Policy();
        $policy->setLabel($name);
        $policy->setPolicy($policyString);

        if ($user) {
            // attach to user
            $this->linkPolicy($policy, $user);
        }

        if ($bucket) {
            // attach to bucket
            $this->linkPolicy($policy, $bucket);
        }

        $this->entityManager->persist($policy);
        if ($flush) {
            $this->entityManager->flush();
        }

        return $policy;
    }

    /**
     * @param User|Bucket $object
     */
    public function unlinkPolicy(Policy $policy, mixed $object, bool $flush = false): void
    {
        if ($object instanceof User) {
            if ($policy->getUser() === $object) {
                $object->removePolicy($policy);
                $policy->setUser(null);
            } else {
                throw new \InvalidArgumentException('Policy is not linked to user');
            }
        } elseif ($object instanceof Bucket) {
            if ($policy->getBucket() === $object) {
                $object->removePolicy($policy);
                $policy->setBucket(null);
            } else {
                throw new \InvalidArgumentException('Policy is not linked to bucket');
            }
        } else {
            throw new \InvalidArgumentException('Invalid object type');
        }

        $this->entityManager->persist($policy);
        if ($flush) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param User|Bucket $object
     */
    public function linkPolicy(Policy $policy, mixed $object, bool $flush = false): void
    {
        if ($object instanceof User) {
            if (null != $policy->getUser()) {
                throw new \InvalidArgumentException('Policy is already linked to a user');
            } else {
                $object->addPolicy($policy);
            }
        } elseif ($object instanceof Bucket) {
            if (null != $policy->getBucket()) {
                throw new \InvalidArgumentException('Policy is already linked to a bucket');
            } else {
                $object->addPolicy($policy);
            }
        } else {
            throw new \InvalidArgumentException('Invalid object type');
        }

        $this->entityManager->persist($policy);
        if ($flush) {
            $this->entityManager->flush();
        }
    }
}
