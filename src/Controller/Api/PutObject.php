<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\User;
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

class PutObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'put_object', methods: ['PUT'], requirements: ['key' => '.+'])]
    public function putObject(AuthorizationService $authorizationService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
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
                    ['action' => 's3:PutObject', 'resource' => $bucket->getIdentifier().'/'.$key],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, $key);

        // evaluate conditional headers
        try {
            $requestService->evaluateConditionalPutHeaders($request, $file);
        } catch (PreconditionFailedException $e) {
            return $responseService->createPreconditionFailedResponse();
        }

        // create new file and filepart from request content
        $newFile = $bucketService->createFileAndFilepartFromResource($bucket, $key, $request->headers->get('content-type', ''), $request->getContent(true));
        $bucketService->saveFileAndParts($newFile);
        $bucketService->makeVersionActive($newFile, $file, true);

        $headers = ['ETag' => $file->getEtag()];
        if ($file->getVersion()) {
            $headers['x-amz-version-id'] = $file->getVersion();
        }

        return $responseService->createResponse(
            [],
            204,
            '',
            $headers,
        );
    }
}
