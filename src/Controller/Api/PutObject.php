<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PutObject extends AbstractController
{
    #[Route('/{bucket}/{key}', name: 'put_object', methods: ['PUT'], requirements: ['key' => '.+'])]
    public function putObject(ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createResponse(
                [
                    'Error' => [
                        'Code' => 'AccessDenied',
                        'Message' => 'Access Denied',
                    ],
                ],
                403
            );
        }

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, $key);
        if ($file) {
            // existing file
            if ($request->headers->get('if-none-match', '') === '*') {
                // if-none-match is set, abort
                return new Response('Precondition Failed', 412, ['X-Message' => 'Key already exists in bucket']);
            }
        } else {
            $file = new File();
            $file->setBucket($bucket);
            $file->setName($key);
            $file->setPath($bucketService->getUnusedPath($bucket));
        }

        // check if-match header
        if ($request->headers->get('if-match', '') !== '') {
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

        $path = 'storage'.DIRECTORY_SEPARATOR.$bucket->getName().DIRECTORY_SEPARATOR.$file->getPath();
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
        $file->setEtag(md5_file($path));

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
