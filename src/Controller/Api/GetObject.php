<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Object\NotModifiedException;
use App\Exception\Object\PreconditionFailedException;
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

        /*
         * There's a difference between not passing a versionId and passing a versionId of null.
         */
        $versionId = $request->query->getString('versionId', '');
        if ('null' == $versionId) {
            $versionId = null;
        }

        if ('' !== $versionId) {
            // dealing with specific version requires a different action
            $requiredAction = 's3:GetObjectVersion';
        } else {
            // use current version
            $requiredAction = 's3:GetObject';
        }

        /** @var ?User $user */
        $user = $this->getUser();
        try {
            $authorizationService->requireAll(
                $user,
                [
                    ['action' => $requiredAction, 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        // check if key exists in bucket
        $file = $bucketService->getFile($bucket, $key, $versionId);
        if (!$file) {
            // API states it returns 404 but in reality it returns 403 forbidden
            return $responseService->createForbiddenResponse();
        } elseif ($file->isDeleteMarker()) {
            return $responseService->createResponse(
                [],
                404,
                'text/html',
                [
                    'x-amz-delete-marker' => 'true',
                    'x-amz-version-id' => $file->getVersion(),
                ]
            );
        }

        // Do we need to serve the file?
        try {
            $requestService->evaluateConditionalGetHeaders($request, $file);
        } catch (PreconditionFailedException $e) {
            return $responseService->createPreconditionFailedResponse();
        } catch (NotModifiedException $e) {
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

        $parts = [];
        foreach ($file->getFileparts() as $filepart) {
            $parts[$filepart->getPartNumber()] = $bucketService->getAbsolutePartPath($filepart);
        }
        ksort($parts);

        $headers = [
            'Content-Length' => $file->getSize(),
            'Last-Modified' => $file->getMtime()->format('D, d M Y H:i:s').' GMT',
            'ETag' => $file->getEtag(),
            'Accept-Ranges' => 'bytes',
        ];

        if ($file->getContentType()) {
            $headers['Content-Type'] = $file->getContentType();
        }

        if ('' !== $versionId) {
            $headers['x-amz-version-id'] = $file->getVersion() ?? 'null';
        }

        if (0 !== $rangeStart || $rangeEnd !== $file->getSize() - 1) {
            $headers['Content-Range'] = 'bytes '.$rangeStart.'-'.$rangeEnd.'/'.$file->getSize();
            $headers['Content-Length'] = $rangeEnd - $rangeStart + 1;
        } else {
            // not doing ranged request
            $rangeStart = -1;
            $rangeEnd = -1;
        }

        if ('HEAD' == $request->getMethod()) {
            return $responseService->createResponse([], 200, $file->getContentType(), $headers);
        } else {
            return $responseService->createFileStreamResponse($parts, $rangeStart, $rangeEnd, $headers);
        }
    }
}
