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

        /*
         * There's a difference between not passing a versionId and passing a versionId of null.
         */
        $versionId = $request->query->getString('versionId', '');
        if ('null' == $versionId) {
            $versionId = null;
        }

        if ('' !== $versionId) {
            // deleting a specific version requires a different action, even when deleting null version on non-versioned bucket
            $requiredAction = 's3:DeleteObjectVersion';
        } else {
            // regular delete
            $requiredAction = 's3:DeleteObject';
        }

        /** @var ?User $user */
        $user = $this->getUser();
        try {
            $authorizationService->requireAll(
                $user,
                [
                    ['action' => $requiredAction, 'resource' => $bucket->getIdentifier().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, $key, $versionId);
        // file must exists, and must not be a delete marker unless versionId is specified
        if ($file && (!$file->isDeleteMarker() || '' !== $versionId)) {
            // existing file
            if (
                $request->headers->has('if-match')
                && !$requestService->etagHeaderMatches($request->headers->get('if-match'), $file->getEtag())
            ) {
                return $responseService->createPreconditionFailedResponse();
            }

            try {
                if ('' === $versionId) {
                    // delete file (result depends on bucket versioning setting)
                    $deletedFile = $bucketService->deleteFile($file, true, true);

                    if ($deletedFile->isDeleteMarker()) {
                        return $responseService->createResponse([], 204, '', [
                            'x-amz-delete-marker' => 'true',
                            'x-amz-version-id' => $deletedFile->getVersion(),
                        ]);
                    } else {
                        return $responseService->createResponse([], 204, '', [
                            'x-amz-delete-marker' => 'false',
                        ]);
                    }
                } else {
                    // delete specific file version or delete marker
                    $bucketService->deleteFileVersion($file, true, true);

                    // x-amz-delete-marker is true if the file was a delete marker BEFORE deleting
                    return $responseService->createResponse([], 204, '', [
                        'x-amz-delete-marker' => $file->isDeleteMarker() ? 'true' : 'false',
                    ]);
                }
            } catch (\Exception $e) {
                return $responseService->createErrorResponse(500, 'DeleteFailed', 'Delete Failed');
            }
        } else {
            return $responseService->createForbiddenResponse();
        }
    }
}
