<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CreateMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function createMultipartUpload(AuthorizationService $authorizationService, GeneratorService $generatorService, ResponseService $responseService, BucketService $bucketService, string $bucket, string $key): Response
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
