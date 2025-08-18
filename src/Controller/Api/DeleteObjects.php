<?php

namespace App\Controller\Api;

use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DeleteObjects extends AbstractController
{
    public function deleteObjects(ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }
        if (str_starts_with($bucket->getPath(), DIRECTORY_SEPARATOR) || str_starts_with($bucket->getPath(), '\\')) {
            // full path
            $bucketPath = $bucket->getPath();
        } else {
            // relative path from standard storage location
            $bucketPath = $this->getParameter('bucket_storage_path').DIRECTORY_SEPARATOR.$bucket->getPath();
        }

        try {
            $deleteRequest = new \SimpleXMLElement($request->getContent());
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'MalformedXML', 'Malformed XML');
        }

        // must be Delete request
        if ('Delete' !== $deleteRequest->getName()) {
            return $responseService->createErrorResponse(400, 'InvalidRequest', 'Invalid Request 1');
        }

        if (0 === count($deleteRequest->Object)) {
            // must have at least one Object
            return $responseService->createErrorResponse(400, 'InvalidRequest', 'Invalid Request 2');
        }

        $quiet = 'true' === (string) ($deleteRequest->Quiet ?? 'false');
        $deleted = [];
        $errors = [];
        foreach ($deleteRequest->Object as $object) {
            if (!$object->Key) {
                return $responseService->createErrorResponse(400, 'InvalidRequest', 'Invalid Request 3');
            }

            $file = $bucketService->getFile($bucket, (string) $object->Key);
            if (!$file) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'NoSuchKey',
                    'Message' => 'The specified key does not exist.',
                    // VersionId
                ];
                continue;
            }

            // check if ETag is present
            if ($object->ETag && $file->getEtag() !== (string) $object->ETag) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'ETagMismatch',
                    'Message' => 'ETag Mismatch',
                    // VersionId
                ];
                continue;
            }

            // check if LastModifiedTime is present
            if ($object->LastModifiedTime && $file->getMtime() !== new \DateTime((string) $object->LastModifiedTime)) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'LastModifiedTimeMismatch',
                    'Message' => 'LastModifiedTime Mismatch',
                    // VersionId
                ];
                continue;
            }

            if ($object->Size && $file->getSize() !== (int) $object->Size) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'SizeMismatch',
                    'Message' => 'Size Mismatch',
                    // VersionId
                ];
                continue;
            }

            // ready to delete
            $parts = [];
            foreach ($file->getFileparts() as $filepart) {
                $parts[$filepart->getPartNumber()] = $bucketPath.DIRECTORY_SEPARATOR.$filepart->getPath();
            }

            // delete file from database
            $bucketService->deleteFile($file);

            // delete parts from filesystem
            $failed = false;
            foreach ($parts as $path) {
                if (file_exists($path)) {
                    if (!unlink($path)) {
                        $failed = true;
                    }
                }
            }

            if ($failed) {
                $errors[] = [
                    'Key' => (string) $object->Key,
                    'Code' => 'FilesystemError',
                    'Message' => 'Filesystem Error',
                    // VersionId
                ];
            } elseif (!$quiet) {
                $deleted[] = [
                    'Key' => (string) $object->Key,
                    'DeleteMarker' => 'false', // unsupported
                    // VersionId
                ];
            }
        }

        $result = [
            'DeleteResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
            ],
        ];

        if (count($deleted) > 0) {
            $result['DeleteResult']['#Deleted'] = $deleted;
        }
        if (count($errors) > 0) {
            $result['DeleteResult']['#Error'] = $errors;
        }

        return $responseService->createResponse($result);
    }
}
