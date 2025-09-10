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

class ListParts extends AbstractController
{
    // Routing handled by RouteListener
    public function listParts(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId): Response
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
                    ['action' => 's3:ListMultipartUploadParts', 'resource' => $bucket->getIdentifier().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $file = $bucketService->getFileMpu($bucket, $key, $uploadId);
        if (!$file) {
            return $responseService->createErrorResponse(404, 'NoSuchUpload', 'The specified multipart upload does not exist.');
        }

        $partNumberMarker = $request->query->getInt('part-number-marker', 0);
        $maxParts = $request->query->getInt('max-parts', 1000);

        $partList = $bucketService->listMpuParts($file, $partNumberMarker, $maxParts);
        $data = [
            'ListPartsResult' => [
                'Bucket' => $bucket->getName(),
                'Key' => $file->getName(),
                'MaxParts' => $maxParts,
                'IsTruncated' => $partList->isTruncated() ? 'true' : 'false',
                'UploadId' => $file->getMultipartUploadId(),
                'Owner' => [
                    'ID' => $bucket->getOwner()->getIdentifier(),
                ],
                'StorageClass' => 'STANDARD',
            ],
        ];

        if ($partNumberMarker) {
            $data['ListPartsResult']['PartNumberMarker'] = $partNumberMarker;
        }

        if ($partList->isTruncated()) {
            $data['ListPartsResult']['NextPartNumberMarker'] = $partList->getNextMarker();
        }

        if (count($partList->getFileparts()) > 0) {
            $data['ListPartsResult']['#Part'] = [];
            foreach ($partList->getFileparts() as $filepart) {
                $item = [
                    'ETag' => $filepart->getEtag(),
                    'LastModified' => $filepart->getMtime()->format('c'),
                    'PartNumber' => $filepart->getPartNumber(),
                    'Size' => $filepart->getSize(),
                ];
                $data['ListPartsResult']['#Part'][] = $item;
            }
        }

        return $responseService->createResponse($data);
    }
}
