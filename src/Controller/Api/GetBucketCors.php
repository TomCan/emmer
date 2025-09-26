<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\CorsService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetBucketCors extends AbstractController
{
    // Routing handled by RouteListener
    public function getBucketCors(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, CorsService $corsService, Request $request, string $bucket): Response
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
                    ['action' => 's3:GetBucketCORS', 'resource' => $bucket->getIdentifier()],
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
            if (null == $bucket->getCorsRules() || 0 == $bucket->getCorsRules()->count()) {
                return $responseService->createErrorResponse(404, 'NoBucketCorsFound', 'No bucket cors found');
            }

            $rules = $corsService->convertRulesToXmlArray($bucket->getCorsRules()->toArray());

            return $responseService->createResponse(
                $rules,
            );
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(500, 'UnexpectedError', 'Unexpected error retrieving bucket cors.');
        }
    }
}
