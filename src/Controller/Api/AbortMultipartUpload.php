<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AbortMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function abortMultipartUpload(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, string $bucket, string $key, string $uploadId): Response
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
                    ['action' => 's3:AbortMultipartUpload', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $headers = [];
        $file = $bucketService->getFileMpu($bucket, $key, $uploadId);
        if ($file) {
            $bucketService->deleteFileVersion($file, true, true);

            return $responseService->createResponse(
                [],
                204,
                'text/plains',
                $headers,
            );
        } else {
            return $responseService->createErrorResponse(
                404,
                'NoSuchUpload',
                'The specified multipart upload does not exist. The upload ID might be invalid, or the multipart upload might have been aborted or completed.'
            );
        }
    }
}
