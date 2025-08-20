<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AuthorizationService
{
    public function __construct(
        private PolicyResolver $policyResolver,
        private PrincipalService $principalService,
    ) {
    }

    /**
     * @param array<array{action: string, resource: string}> $rules
     */
    public function requireAll(?User $user, array $rules, mixed ...$policyObjects): void
    {
        $principals = $this->principalService->getPrincipals($user);

        // make sure user is in $policyObjects is not null
        if ($user && !in_array($user, $policyObjects)) {
            $policyObjects[] = $user;
        }
        $statements = $this->policyResolver->convertPolicies(...$policyObjects);

        foreach ($rules as $rule) {
            if (!$this->policyResolver->isCallPermitted($statements, $principals, $rule['action'], $rule['resource'])) {
                throw new AccessDeniedException();
            }
        }
    }

    /**
     * @param array<array{action: string, resource: string}> $rules
     */
    public function requireAny(?User $user, array $rules, mixed ...$policyObjects): void
    {
        $principals = $this->principalService->getPrincipals($user);

        // make sure user is in $policyObjects is not null
        if ($user && !in_array($user, $policyObjects)) {
            $policyObjects[] = $user;
        }
        $statements = $this->policyResolver->convertPolicies(...$policyObjects);

        $matchedAny = false;
        foreach ($rules as $rule) {
            if ($this->policyResolver->isCallPermitted($statements, $principals, $rule['action'], $rule['resource'])) {
                $matchedAny = true;
                break;
            }
        }

        if (!$matchedAny) {
            throw new AccessDeniedException();
        }
    }
}
