<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Object\InvalidManifestException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CompleteMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function completeMultipartUpload(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId): Response
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

        $file = $bucketService->getFileMpu($bucket, $key, $uploadId);
        if ($file) {
            try {
                $completeRequest = new \SimpleXMLElement($request->getContent());
            } catch (\Exception $e) {
                return $responseService->createErrorResponse(400, 'MalformedXML', 'Malformed XML');
            }

            try {
                $targetFile = $bucketService->completeMultipartUpload($file, $completeRequest);

                return $responseService->createResponse(
                    [
                        'CompleteMultipartUploadResult' => [
                            '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                            'Location' => $bucket->getName().'/'.$key,
                            'Bucket' => $bucket->getName(),
                            'Key' => $key,
                            'ETag' => $targetFile->getEtag(),
                        ],
                    ]
                );
            } catch (InvalidManifestException $e) {
                return $responseService->createErrorResponse(400, 'InvalidManifest', 'Invalid Manifest ('.(string) $e->getCode().')');
            } catch (\Exception $e) {
                return $responseService->createErrorResponse(500, 'CompleteMultipartUploadFailed', 'Complete Multipart Upload Failed'.$e->getMessage());
            }
        } else {
            return $responseService->createErrorResponse(
                404,
                'NoSuchUpload',
                'The specified multipart upload does not exist. The upload ID might be invalid, or the multipart upload might have been aborted or completed.'
            );
        }
    }
}
