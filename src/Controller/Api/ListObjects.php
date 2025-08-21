<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ListObjects extends AbstractController
{
    #[Route('/{bucket}', name: 'list_objects', methods: ['GET'])]
    public function listObjects(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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
                    ['action' => 's3:ListObjects', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$request->query->getString('prefix', '')],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
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
            $fetchOwner = 'true' == $request->query->getString('fetch-owner', '');
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
        if ($encodingType && 'url' != $encodingType) {
            $encodingType = '';
        }

        $objectList = $bucketService->listFiles($bucket, $prefix, $delimiter, $marker, $markerType, $maxKeys);
        $data = [
            'ListBucketResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                'Name' => $bucket->getName(),
                'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                'MaxKeys' => $maxKeys,
                'IsTruncated' => $objectList->isTruncated() ? 'true' : 'false',
            ],
        ];

        // add EncodingType is set
        if ($encodingType) {
            $data['ListBucketResult']['EncodingType'] = $encodingType;
        }

        // add Delimiter if set
        if ($delimiter) {
            $data['ListBucketResult']['Delimiter'] = ('url' == $encodingType) ? urlencode($delimiter) : $delimiter;
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
                $data['ListBucketResult']['StartAfter'] = ('url' == $encodingType) ? urlencode($startAfter) : $startAfter;
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
                    'Key' => ('url' == $encodingType) ? urlencode($file->getName()) : $file->getName(),
                    'LastModified' => $file->getMtime()->format('c'),
                    'ETag' => '"'.$file->getEtag().'"',
                    'Size' => $file->getSize(),
                    'StorageClass' => 'STANDARD',
                ];
                if ($fetchOwner) {
                    $item['Owner'] = [
                        'ID' => $bucket->getOwner()->getIdentifier(),
                    ];
                }
                $data['ListBucketResult']['#Contents'][] = $item;
            }
        }

        if (count($objectList->getCommonPrefixes()) > 0) {
            $data['ListBucketResult']['#CommonPrefixes'] = [];
            foreach ($objectList->getCommonPrefixes() as $prefix) {
                $data['ListBucketResult']['#CommonPrefixes'][] = [
                    'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                ];
            }
        }

        return $responseService->createResponse($data);
    }
}
