<?php

namespace App\Service;

use App\Entity\User;

class PrincipalService
{
    /**
     * @return string[]
     */
    public function getPrincipals(?User $user): array
    {
        $principals = [];

        if (null === $user) {
            $principals[] = 'emr:usr:@anonymous';
        } else {
            $principals[] = 'emr:usr:'.$user->getEmail();
            // add roles, but note ROLE_USER as every user has this role
            foreach ($user->getRoles() as $role) {
                if ('ROLE_USER' !== $role) {
                    if (str_starts_with($role, 'ROLE_')) {
                        $role = strtolower(substr($role, 5));
                    } else {
                        $role = strtolower($role);
                    }
                    $principals[] = 'emr:role:'.$role;
                }
            }
        }

        return $principals;
    }
}
