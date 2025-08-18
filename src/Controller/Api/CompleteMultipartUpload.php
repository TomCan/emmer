<?php

namespace App\Controller\Api;

use App\Entity\File;
use App\Entity\Filepart;
use App\Service\BucketService;
use App\Service\GeneratorService;
use App\Service\RequestService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CompleteMultipartUpload extends AbstractController
{
    // Routing handled by RouteListener
    public function completeMultipartUpload(GeneratorService $generatorService, RequestService $requestService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket, string $key, string $uploadId): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        $file = $bucketService->getFile($bucket, '{emmer:mpu:'.$uploadId.'}'.$key);
        if ($file) {
            try {
                $completeRequest = new \SimpleXMLElement($request->getContent());
            } catch (\Exception $e) {
                return $responseService->createErrorResponse(400, 'MalformedXML', 'Malformed XML');
            }

            if ('CompleteMultipartUpload' !== $completeRequest->getName()) {
                return $responseService->createErrorResponse(400, 'InvalidRequest', 'Invalid Request');
            }

            $parts = [];
            $fileParts = $file->getFileparts()->toArray();
            ksort($fileParts);
            foreach ($completeRequest->Part as $part) {
                $partNumber = (int) $part->PartNumber;
                if ($partNumber !== count($parts) + 1) {
                    return $responseService->createErrorResponse(400, 'InvalidPartOrder', 'Invalid Part Order');
                }

                $etag = (string) $part->ETag;
                if (str_starts_with($etag, '"') && str_ends_with($etag, '"')) {
                    $etag = substr($etag, 1, -1);
                }
                if (!isset($fileParts[$partNumber - 1]) || ('' !== $etag && $fileParts[$partNumber - 1]->getEtag() !== $etag)) {
                    return $responseService->createErrorResponse(400, 'InvalidPart', 'Invalid Part');
                }

                $parts[] = $fileParts[$partNumber - 1];
            }

            // we have all parts in the order we need them. Combine them into a single file
            $targetFile = $bucketService->getFile($bucket, $key);
            if (null == $targetFile) {
                $targetFile = new File();
                $targetFile->setBucket($bucket);
                $targetFile->setName($key);
            } else {
                // existing file, delete existing parts
                foreach ($targetFile->getFileparts() as $part) {
                    $bucketService->deleteFilepart($part, true, false);
                }
                $targetFile->getFileparts()->clear();
                $bucketService->saveFile($targetFile);
            }

            $targetPart = new Filepart();
            $targetFile->addFilepart($targetPart);
            $targetPart->setPartNumber(1);
            $targetPart->setName($generatorService->generateId(32));
            $targetPart->setPath($bucketService->getUnusedPath($bucket));

            $outputPath = $bucketService->getAbsolutePartPath($targetPart);
            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            $outputFile = fopen($outputPath, 'wb');
            foreach ($parts as $part) {
                $partPath = $bucketService->getAbsolutePartPath($part);
                $partFile = fopen($partPath, 'rb');
                stream_copy_to_stream($partFile, $outputFile);
                fclose($partFile);
            }
            fclose($outputFile);

            $targetFile->setSize(filesize($outputPath));
            $targetFile->setEtag(md5_file($outputPath));
            $targetFile->setMtime(new \DateTime());

            $targetPart->setSize($targetFile->getSize());
            $targetPart->setEtag($targetFile->getEtag());
            $targetPart->setMtime($targetFile->getMtime());

            // first delete the old file, then save the new one to prevent duplicate keys
            $bucketService->deleteFile($file, true);
            $bucketService->saveFile($targetFile);

            return $responseService->createResponse(
                [
                    'CompleteMultipartUploadResult' => [
                        '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                        'Location' => $bucket->getName().'/'.$key,
                        'Bucket' => $bucket->getName(),
                        'Key' => $key,
                        'ETag' => '"'.$targetFile->getEtag().'"',
                    ],
                ]
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
