<?php

namespace App\Controller\Api;

use App\Entity\File;
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

class GetObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'get_object', methods: ['HEAD', 'GET'], requirements: ['key' => '.+'])]
    public function getObject(AuthorizationService $authorizationService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
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
                    ['action' => 's3:GetObject', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        // check if key exists in bucket
        $file = $bucketService->getFile($bucket, $key);
        if (!$file) {
            // API states it returns 404 but in reality it returns 403 forbidden
            return $responseService->createForbiddenResponse();
        }

        // Do we need to serve the file?
        $action = $requestService->evaluateConditionalHeaders($request, $file->getEtag(), $file->getMtime());
        if (412 === $action) {
            return $responseService->createPreconditionFailedResponse();
        }
        if (304 === $action) {
            return $responseService->createNotModifiedResponse();
        }

        $rangeStart = 0;
        $rangeEnd = $file->getSize() - 1;
        if ($request->headers->has('range')) {
            try {
                list($rangeStart, $rangeEnd) = $requestService->getRange($request->headers->get('range'), $file->getSize() - 1);
            } catch (\InvalidArgumentException $e) {
                return $responseService->createErrorResponse(
                    400,
                    str_replace(' ', '', $e->getMessage()),
                    $e->getMessage()
                );
            }
        }

        if (str_starts_with($bucket->getPath(), DIRECTORY_SEPARATOR)) {
            $bucketPath = $bucket->getPath();
        } else {
            $bucketPath = $this->getParameter('bucket_storage_path').DIRECTORY_SEPARATOR.$bucket->getPath();
        }

        $parts = [];
        foreach ($file->getFileparts() as $filepart) {
            $parts[$filepart->getPartNumber()] = $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath();
        }
        ksort($parts);

        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => $file->getSize(),
            'Last-Modified' => $file->getMtime()->format('D, d M Y H:i:s').' GMT',
            'ETag' => '"'.$file->getEtag().'"',
            'Accept-Ranges' => 'bytes',
        ];

        if (0 !== $rangeStart || $rangeEnd !== $file->getSize() - 1) {
            $headers['Content-Range'] = 'bytes '.$rangeStart.'-'.$rangeEnd.'/'.$file->getSize();
            $headers['Content-Length'] = $rangeEnd - $rangeStart + 1;
        } else {
            // not doing ranged request
            $rangeStart = -1;
            $rangeEnd = -1;
        }

        if ('HEAD' == $request->getMethod()) {
            return $responseService->createResponse([], 200, '', $headers);
        } else {
            return $responseService->createFileStreamResponse($parts, $rangeStart, $rangeEnd, $headers);
        }
    }
}
