<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadPart extends AbstractController
{
    public function uploadPart(GeneratorService $generatorService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId, int $partNumber): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        if ($partNumber < 1 || $partNumber > 10000) {
            return $responseService->createErrorResponse('400', 'InvalidPart', 'The specified part number is not valid.');
        }

        // check if key already exists in bucket
        $file = $bucketService->getFile($bucket, '{emmer:mpu:'.$uploadId.'}'.$key);
        if ($file) {
            // multipart upload exists
            $filePart = new Filepart();
            $filePart->setPartNumber($partNumber);
            $filePart->setName($generatorService->generateId(32));
            $filePart->setPath($bucketService->getUnusedPath($bucket));
            $file->addFilepart($filePart);

            $path = $bucketService->getAbsolutePartPath($filePart);
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
            $filePart->setMtime($file->getMtime());
            $filePart->setSize($bytesWritten);
            $filePart->setEtag(md5_file($path));

            $bucketService->saveFile($file);

            return new Response(
                '',
                200,
                [
                    'ETag' => $filePart->getEtag(),
                ]
            );
        } else {
            // need exisiting multipart upload
            return $responseService->createErrorResponse('404', 'NoSuchUpload', 'The specified multipart upload does not exist. The upload ID may be invalid, or the multipart upload may have been aborted or completed.');
        }
    }
}
