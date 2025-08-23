<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Policy\InvalidPolicyException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\PolicyResolver;
use App\Service\PolicyService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetBucketPolicy extends AbstractController
{
    // Routing handled by RouteListener
    public function getBucketPolicy(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, PolicyService $policyService, PolicyResolver $policyResolver, Request $request, string $bucket): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        /** @var ?User $user */
        $user = $this->getUser();
        try {
            $authorizationService->requireAll(
                $user,
                [
                    ['action' => 's3:GetBucketPolicy', 'resource' => $bucket->getIdentifier()],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        if ($request->query->has('x-amz-expected-bucket-owner')) {
            if ($request->query->get('x-amz-expected-bucket-owner') !== $bucket->getOwner()->getIdentifier()) {
                return $responseService->createForbiddenResponse();
            }
        }

        try {
            $statements = $policyResolver->convertPolicies($bucket);
            try {
                $policy = $policyService->createPolicyFromStatements($statements);

                return $responseService->createResponse(
                    (string) $policy->getPolicy(),
                    200,
                    'application/json'
                );
            } catch (InvalidPolicyException $e) {
                // bucket does not contain valid policy
                return $responseService->createErrorResponse(404, 'NoBucketPolicyFound', 'No bucket policy found.'.$e->getMessage());
            }
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketPolicyGetFailed', 'Failed to get bucket policy.'.$e->getMessage());
        }
    }
}
