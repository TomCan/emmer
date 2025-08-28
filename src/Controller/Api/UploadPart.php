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

class UploadPart extends AbstractController
{
    public function uploadPart(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId, int $partNumber): Response
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
                    ['action' => 's3:PutObject', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        if ($partNumber < 1 || $partNumber > 10000) {
            return $responseService->createErrorResponse(400, 'InvalidPart', 'The specified part number is not valid.');
        }

        // check if key already exists in bucket
        $file = $bucketService->getFileMpu($bucket, $key, $uploadId);
        if ($file) {
            // multipart upload exists, create new part from request content
            $filePart = $bucketService->createFilePartFromResource($file, $partNumber, $request->getContent(true));
            $bucketService->saveFile($file, true);

            return new Response(
                '',
                200,
                [
                    'ETag' => $filePart->getEtag(),
                ]
            );
        } else {
            // need exisiting multipart upload
            return $responseService->createErrorResponse(404, 'NoSuchUpload', 'The specified multipart upload does not exist. The upload ID may be invalid, or the multipart upload may have been aborted or completed.');
        }
    }
}
