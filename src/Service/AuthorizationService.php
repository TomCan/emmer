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
        $this->require($user, $rules, true, ...$policyObjects);
    }

    /**
     * @param array<array{action: string, resource: string}> $rules
     */
    public function requireAny(?User $user, array $rules, mixed ...$policyObjects): void
    {
        $this->require($user, $rules, false, ...$policyObjects);
    }

    /**
     * @param array<array{action: string, resource: string}> $rules
     */
    public function require(?User $user, array $rules, bool $all, mixed ...$policyObjects): void
    {
        // root user has all permissions and cannot be denied
        if ($user && $user->hasRole('ROOT')) {
            return;
        }

        $principals = $this->principalService->getPrincipals($user);

        // make sure user is in $policyObjects if not null
        if ($user && !in_array($user, $policyObjects)) {
            $policyObjects[] = $user;
        }
        $statements = $this->policyResolver->convertPolicies(...$policyObjects);

        $matchedAny = false;
        foreach ($rules as $rule) {
            if ($this->policyResolver->isCallPermitted($statements, $principals, $rule['action'], $rule['resource'])) {
                $matchedAny = true;
                break;
            } elseif ($all) {
                // need to match all rules
                throw new AccessDeniedException();
            }
        }

        if (!$matchedAny) {
            throw new AccessDeniedException();
        }
    }
}
