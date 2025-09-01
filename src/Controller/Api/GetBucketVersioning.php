<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetBucketVersioning extends AbstractController
{
    public function getBucketVersioning(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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
                    ['action' => 's3:GetBucketVersioning', 'resource' => $bucket->getIdentifier()],
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

        return $responseService->createResponse(
            [
                'VersioningConfiguration' => [
                    'Status' => $bucket->isVersioned() ? 'Enabled' : 'Suspended',
                ],
            ],
            200,
        );
    }
}
