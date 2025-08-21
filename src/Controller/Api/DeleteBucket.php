<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Bucket\BucketNotEmptyException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DeleteBucket extends AbstractController
{
    #[Route('/{bucket}', name: 'delete_bucket', methods: ['DELETE'])]
    public function deleteBucket(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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
                    ['action' => 's3:DeleteBucket', 'resource' => $bucket->getIdentifier()],
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
            $bucketService->deleteBucket($bucket);
        } catch (BucketNotEmptyException $e) {
            return $responseService->createErrorResponse(400, 'BucketNotEmpty', 'The bucket must be empty.');
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketDeleteFailed', 'Failed to delete bucket.');
        }

        return $responseService->createResponse(
            [],
            204,
            'text/plain'
        );
    }
}
