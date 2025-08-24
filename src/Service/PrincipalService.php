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
            $principals[] = 'emr:user:@anonymous';
        } else {
            $principals[] = $user->getIdentifier();
            // add roles, but note USER as every user has this role, nor ROOT as this is treated seperately
            foreach ($user->getRoles() as $role) {
                if ('USER' !== $role && 'ROOT' !== $role) {
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
