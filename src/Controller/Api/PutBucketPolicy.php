<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Policy\InvalidPolicyException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\PolicyService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PutBucketPolicy extends AbstractController
{
    // Routing handled by RouteListener
    public function putBucketPolicy(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, PolicyService $policyService, Request $request, string $bucket): Response
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
                    ['action' => 's3:PutBucketPolicy', 'resource' => $bucket->getIdentifier()],
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
            $policyString = $request->getContent();
            $policy = $policyService->createPolicy('bucket policy', $policyString, null, $bucket);
            $bucketService->setBucketPolicy($bucket, $policy, false);

            if ('true' !== (string) $request->headers->get('x-amz-confirm-remove-self-bucket-access', 'false')) {
                // check to see if new policy locked out user
                try {
                    $authorizationService->requireAll($user, [['action' => 's3:PutBucketPolicy', 'resource' => $bucket->getIdentifier()]], $bucket);
                } catch (AccessDeniedException $e) {
                    return $responseService->createErrorResponse(400, 'UnconfirmedBucketLockout', 'Unconfirmed bucket lockout.');
                }
            }

            // save policy to force flush
            $policyService->savePolicy($policy, true);

            return $responseService->createResponse(
                [],
                204,
                'text/plain'
            );
        } catch (InvalidPolicyException $e) {
            return $responseService->createErrorResponse(400, 'InvalidPolicy', 'Invalid policy.');
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketPolicyPutFailed', 'Failed to put bucket policy.');
        }
    }
}
