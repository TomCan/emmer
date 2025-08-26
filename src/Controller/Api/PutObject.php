<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PutObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'put_object', methods: ['PUT'], requirements: ['key' => '.+'])]
    public function putObject(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
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

        if ($file) {
            // existing file
            if ('*' === $request->headers->get('if-none-match', '')) {
                // if-none-match is set, abort
                return new Response('Precondition Failed', 412, ['X-Message' => 'Key already exists in bucket']);
            }

            if ('' !== $request->headers->get('if-match', '')) {
                $etags = explode(',', $request->headers->get('if-match', ''));
                $matches = false;
                foreach ($etags as $etag) {
                    $etag = trim($etag);
                    if (str_starts_with($etag, '"') && str_ends_with($etag, '"')) {
                        // stip quotes
                        $etag = substr($etag, 1, -1);
                    }
                    if ('*' == $etag || $etag === $file->getEtag()) {
                        $matches = true;
                        break;
                    }
                }

                if (!$matches) {
                    return new Response('Precondition Failed', 412, ['X-Message' => 'Etag does not match']);
                }
            }

            // delete current parts
            $file->getFileparts()->clear();
            // create new filepart for $file from request content
            $filepart = $bucketService->createFilePartFromResource($file, 1, $request->getContent(true));
        } else {
            if ('' !== $request->headers->get('if-match', '')) {
                return new Response('Precondition Failed', 412, ['X-Message' => 'ETag does not match']);
            }

            // create new file and filepart from request content
            $file = $bucketService->createFileAndFilepartFromResource($bucket, $key, 0, $request->headers->get('content-type', ''), $request->getContent(true));
        }
        $bucketService->saveFileAndParts($file);

        return new Response(
            '',
            200,
            [
                'ETag' => $file->getEtag(),
            ]
        );
    }
}
