<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\RequestService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DeleteObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'delete_object', methods: ['DELETE'], requirements: ['key' => '.+'])]
    public function deleteObject(AuthorizationService $authorizationService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
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
                    ['action' => 's3:DeleteObject', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, $key);
        if ($file) {
            // existing file
            if (
                $request->headers->has('if-match')
                && !$requestService->etagHeaderMatches($request->headers->get('if-match'), $file->getEtag())
            ) {
                return $responseService->createPreconditionFailedResponse();
            }

            // get full path to file
            if (str_starts_with($bucket->getPath(), DIRECTORY_SEPARATOR) || str_ends_with($bucket->getPath(), '\\')) {
                // full path
                $bucketPath = $bucket->getPath();
            } else {
                // relative path from standard storage location
                $bucketPath = $this->getParameter('bucket_storage_path').DIRECTORY_SEPARATOR.$bucket->getPath();
            }

            $parts = [];
            foreach ($file->getFileparts() as $filepart) {
                $parts[$filepart->getPartNumber()] = $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath();
            }

            // delete file from database
            $bucketService->deleteFile($file);

            // delete files from filesystem
            $failed = false;
            foreach ($parts as $path) {
                if (file_exists($path)) {
                    if (!unlink($path)) {
                        $failed = true;
                    }
                }
            }

            if ($failed) {
                return $responseService->createErrorResponse(500, 'DeleteFailed', 'Delete Failed');
            }

            return $responseService->createResponse([], 204, 'text/plain', []);
        } else {
            return $responseService->createForbiddenResponse();
        }
    }
}
