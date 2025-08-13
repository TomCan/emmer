<?php

namespace App\Controller\Api;

use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ListObjects extends AbstractController
{
    #[Route('/{bucket}', name: 'list_objects', methods: ['GET'])]
    public function listObjects(ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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

        $type = $request->query->getInt('list-type', 1);
        $markerType = 1;
        if (2 === $type) {
            $marker = $request->query->getString('continuation-token', '');
            $startAfter = $request->query->getString('start-after', '');
            if ($marker && $startAfter) {
                // continuation-token takes priority over start-after
                $startAfter = '';
            } elseif ($startAfter) {
                // only start-after is specified
                $marker = $startAfter;
                $markerType = 2;
            }
            $fetchOwner = $request->query->getString('fetch-owner', '') == 'true';
        } else {
            $type = 1; // force value
            $marker = $request->query->getString('marker', '');
            $startAfter = '';
            $fetchOwner = true;
        }
        $prefix = $request->query->getString('prefix', '');
        $delimiter = $request->query->getString('delimiter', '');
        $maxKeys = $request->query->getInt('max-keys', 100);

        $encodingType = $request->query->getString('encoding-type', '');
        if ($encodingType && $encodingType != 'url') {
            $encodingType = '';
        }

        $objectList = $bucketService->listFiles($bucket, $prefix, $delimiter, $marker, $markerType,  $maxKeys);
        $data = [
            'ListBucketResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                'Name' => $bucket->getName(),
                'Prefix' => ($encodingType == 'url') ? urlencode($prefix) : $prefix,
                'MaxKeys' => $maxKeys,
                'IsTruncated' => $objectList->isTruncated() ? 'true' : 'false',
            ]
        ];

        // add EncodingType is set
        if ($encodingType) {
            $data['ListBucketResult']['EncodingType'] = $encodingType;
        }

        // add Delimiter if set
        if ($delimiter) {
            $data['ListBucketResult']['Delimiter'] = ($encodingType == 'url') ? urlencode($delimiter) : $delimiter;
        }

        if (1 === $type) {
            // add v2 specific data
            if ($marker) {
                $data['ListBucketResult']['Marker'] = $marker;
            }

            if ($objectList->isTruncated()) {
                $data['ListBucketResult']['NextMarker'] = $objectList->getNextMarker();
            }
        } elseif (2 === $type) {
            // add v2 specific data
            $data['ListBucketResult']['KeyCount'] = count($objectList->getFiles()) + count($objectList->getCommonPrefixes());

            // add StartAfter if set
            if ($startAfter) {
                $data['ListBucketResult']['StartAfter'] = ($encodingType == 'url') ? urlencode($startAfter) : $startAfter;
            }

            if ($marker) {
                $data['ListBucketResult']['ContinuationToken'] = $marker;
            }

            if ($objectList->isTruncated()) {
                $data['ListBucketResult']['NextContinuationToken'] = $objectList->getNextMarker();
            }
        }

        if (count($objectList->getFiles()) > 0) {
            $data['ListBucketResult']['#Contents'] = [];
            foreach ($objectList->getFiles() as $file) {
                $item = [
                    'Key' => ($encodingType == 'url') ? urlencode($file->getName()) : $file->getName(),
                    'LastModified' => $file->getMtime()->format('c'),
                    'ETag' => '"'.$file->getEtag().'"',
                    'Size' => $file->getSize(),
                    'StorageClass' => 'STANDARD',
                ];
                if ($fetchOwner) {
                    $item['Owner'] = [
                        'ID' => 'emmer',
                        'DisplayName' => 'Emmer',
                    ];
                }
                $data['ListBucketResult']['#Contents'][] = $item;
            }
        }

        if (count($objectList->getCommonPrefixes()) > 0) {
            $data['ListBucketResult']['#CommonPrefixes'] = [];
            foreach ($objectList->getCommonPrefixes() as $prefix) {
                $data['ListBucketResult']['#CommonPrefixes'][] = [
                    'Prefix' => ($encodingType == 'url') ? urlencode($prefix) : $prefix,
                ];
            }
        }

        return $responseService->createResponse($data);
    }
}
