<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\LifecycleService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DeleteBucketLifecycle extends AbstractController
{
    // Routing handled by RouteListener
    public function deleteBucketLifecycle(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, LifecycleService $lifecycleService, Request $request, string $bucket): Response
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
                    ['action' => 's3:PutLifecycleConfiguration', 'resource' => $bucket->getIdentifier()],
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
            foreach ($bucket->getLifecycleRules() as $rule) {
                $lifecycleService->deleteLifecycleRules($rule, true);
            }

            // valid and saved
            return $responseService->createResponse(
                [],
                204,
                ''
            );
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketLifecycleRulesDeleteFailed', 'Failed to delete bucket lifecycle rules.');
        }
    }
}
