<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\RequestService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CreateMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function createMultipartUpload(GeneratorService $generatorService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        // For now, create a new file with a unique name and handle this on completion
        $id = $generatorService->generateId(64);
        $file = new File();
        $file->setBucket($bucket);
        $file->setName('{emmer:mpu:'.$id.'}'.$key);
        $file->setMtime(new \DateTime());
        $file->setSize(0);
        $file->setEtag('');
        $bucketService->saveFile($file);

        return $responseService->createResponse(
            [
                'InitiateMultipartUploadResult' => [
                    'Bucket' => $bucket->getName(),
                    'Key' => $key,
                    'UploadId' => $id,
                ],
            ],
        );
    }
}
