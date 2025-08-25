<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\HashService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PutObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'put_object', methods: ['PUT'], requirements: ['key' => '.+'])]
    public function putObject(AuthorizationService $authorizationService, GeneratorService $generatorService, HashService $hashService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
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

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, $key);
        if ($file) {
            // existing file
            if ('*' === $request->headers->get('if-none-match', '')) {
                // if-none-match is set, abort
                return new Response('Precondition Failed', 412, ['X-Message' => 'Key already exists in bucket']);
            }

            // get first filepart, which isn't guaranteed to be the first in the collection
            $filePart = null;
            foreach ($file->getFileparts() as $part) {
                if (1 === $part->getPartNumber()) {
                    $filePart = $file->getFileparts()[0];
                    break;
                }
            }
            // force single part
            $file->getFileparts()->clear();
            $file->addFilepart($filePart);
        } else {
            $file = new File();
            $file->setBucket($bucket);
            $file->setName($key);

            $filePart = new Filepart();
            $filePart->setPartNumber(1);
            $filePart->setName($generatorService->generateId(32));
            $filePart->setPath($bucketService->getUnusedPath($bucket));
            $file->addFilepart($filePart);
        }

        // check if-match header
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

        $bucketPath = $bucketService->getAbsoluteBucketPath($bucket);
        $path = $bucketPath.DIRECTORY_SEPARATOR.$filePart->getPath();
        $basePath = dirname($path);
        if (!is_dir($basePath)) {
            mkdir($basePath, 0755, true);
        }

        $contentStream = $request->getContent(true);
        $outputFile = fopen($path, 'wb');
        $bytesWritten = stream_copy_to_stream($contentStream, $outputFile);
        fclose($contentStream);
        fclose($outputFile);

        $file->setMtime(new \DateTime());
        $file->setSize(filesize($path));
        $file->setEtag($hashService->hashFile($file, $bucketPath));

        $filePart->setMtime($file->getMtime());
        $filePart->setSize($file->getSize());
        $filePart->setEtag($file->getEtag());

        $bucketService->saveFile($file);

        return new Response(
            '',
            200,
            [
                'ETag' => $file->getEtag(),
            ]
        );
    }
}
