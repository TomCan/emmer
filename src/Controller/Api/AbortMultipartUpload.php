<?php

namespace App\Controller\Api;

use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\RequestService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AbortMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function abortMultipartUpload(GeneratorService $generatorService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        $headers = [];
        $file = $bucketService->getFile($bucket, '{emmer:mpu:'.$uploadId.'}'.$key);
        if ($file) {
            foreach ($file->getFileparts() as $part) {
                $partPath = $bucketService->getAbsolutePartPath($part);
                $ul = unlink($partPath);
                $headers[] = 'X-Emmer-Part-'.$part->getPartNumber().'-Status: '.$partPath;
            }

            $bucketService->deleteFile($file);

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
