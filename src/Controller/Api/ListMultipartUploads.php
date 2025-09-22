<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ListMultipartUploads extends AbstractController
{
    // Routing handled by RouteListener
    public function listMultipartUploads(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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
                    ['action' => 's3:ListBucketMultipartUploads', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$request->query->getString('prefix', '')],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $keyMarker = $request->query->getString('key-marker', '');
        $uploadIdMarker = $request->query->getString('upload-id-marker', '');
        $prefix = $request->query->getString('prefix', '');
        $delimiter = $request->query->getString('delimiter', '');
        $maxUploads = $request->query->getInt('max-uploads', 100);

        $encodingType = $request->query->getString('encoding-type', '');
        if ($encodingType && 'url' != $encodingType) {
            $encodingType = '';
        }

        $objectList = $bucketService->listMultipartUploads($bucket, $prefix, $delimiter, $keyMarker, $uploadIdMarker, $maxUploads);
        $data = [
            'ListMultipartUploadsResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                'Name' => $bucket->getName(),
                'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                'MaxUploads' => $maxUploads,
                'IsTruncated' => $objectList->isTruncated() ? 'true' : 'false',
            ],
        ];

        // add EncodingType is set
        if ($encodingType) {
            $data['ListMultipartUploadsResult']['EncodingType'] = $encodingType;
        }

        // add Delimiter if set
        if ($delimiter) {
            $data['ListMultipartUploadsResult']['Delimiter'] = ('url' == $encodingType) ? urlencode($delimiter) : $delimiter;
        }

        if ($keyMarker) {
            $data['ListMultipartUploadsResult']['KeyMarker'] = $keyMarker;
        }
        if ($uploadIdMarker) {
            $data['ListMultipartUploadsResult']['UploadIdMarker'] = $uploadIdMarker;
        }

        if ($objectList->isTruncated()) {
            $data['ListMultipartUploadsResult']['NextKeyMarker'] = $objectList->getNextMarker();
            $data['ListMultipartUploadsResult']['NextUploadIdMarker'] = $objectList->getNextMarker2();
        }

        if (count($objectList->getFiles()) > 0) {
            $data['ListMultipartUploadsResult']['#Upload'] = [];
            foreach ($objectList->getFiles() as $file) {
                $item = [
                    'Key' => ('url' == $encodingType) ? urlencode($file->getName()) : $file->getName(),
                    'Initiated' => $file->getCtime()->format('c'),
                    'StorageClass' => 'STANDARD',
                    'Owner' => [
                        'ID' => $bucket->getOwner()->getIdentifier(),
                    ],
                    'UploadId' => $file->getMultipartUploadId(),
                ];
                $data['ListMultipartUploadsResult']['#Upload'][] = $item;
            }
        }

        if (count($objectList->getCommonPrefixes()) > 0) {
            $data['ListMultipartUploadsResult']['#CommonPrefixes'] = [];
            foreach ($objectList->getCommonPrefixes() as $prefix) {
                $data['ListMultipartUploadsResult']['#CommonPrefixes'][] = [
                    'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                ];
            }
        }

        return $responseService->createResponse($data);
    }
}
