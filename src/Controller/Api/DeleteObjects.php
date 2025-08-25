<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteObjects extends AbstractController
{
    public function deleteObjects(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        /** @var ?User $user */
        $user = $this->getUser();

        try {
            $deleteRequest = new \SimpleXMLElement($request->getContent());
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'MalformedXML', 'Malformed XML');
        }

        try {
            $result = $bucketService->deleteObjects($bucket, $user, $deleteRequest);

            return $responseService->createResponse($result);
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'DeleteObjectsFailed', 'DeleteObjects Failed');
        }
    }
}
