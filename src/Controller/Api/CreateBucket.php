<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CreateBucket extends AbstractController
{
    #[Route('/{bucket}', name: 'create_bucket', methods: ['PUT'])]
    public function createBucket(AuthorizationService $authorizationService, GeneratorService $generatorService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
    {
        /** @var ?User $user */
        $user = $this->getUser();
        if (!$user) {
            // need a user to create a bucket as it requires a bucket owner
            return $responseService->createForbiddenResponse();
        }

        try {
            $authorizationService->requireAll(
                $user,
                [
                    ['action' => 's3:CreateBucket', 'resource' => 'emr:bucket:'.$bucket],
                ],
                $user,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $existingBucket = $bucketService->getBucket($bucket);
        if ($existingBucket) {
            if ($existingBucket->getOwner() === $this->getUser()) {
                return $responseService->createErrorResponse(409, 'BucketAlreadyOwnedByYou', 'Bucket already owned by you.');
            } else {
                return $responseService->createErrorResponse(409, 'BucketAlreadyExists', 'Bucket already exists.');
            }
        }

        try {
            $createdBucket = $bucketService->createBucket($bucket, '', '', $user, true, true);
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(500, 'BucketCreationFailed', 'Bucket creation failed.'.$e->getMessage());
        }

        return $responseService->createResponse(
            [],
            200,
            'text/plain',
            [
                'Location' => $this->generateUrl('head_bucket', ['bucket' => $createdBucket->getName()]),
                'x-amz-bucket-arn' => $createdBucket->getIdentifier(),
            ]
        );
    }
}
