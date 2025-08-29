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

class ListObjectVersions extends AbstractController
{
    // Routing handled by RouteListener
    public function listObjectVersions(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request, string $bucket): Response
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
                    ['action' => 's3:ListBucketVersions', 'resource' => 'emr:bucket:'.$bucket->getName().'/'.$request->query->getString('prefix', '')],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $keyMarker = $request->query->getString('key-marker', '');
        $versionMarker = $request->query->getString('version-marker', '');
        $prefix = $request->query->getString('prefix', '');
        $delimiter = $request->query->getString('delimiter', '');
        $maxKeys = $request->query->getInt('max-keys', 100);

        $encodingType = $request->query->getString('encoding-type', '');
        if ($encodingType && 'url' != $encodingType) {
            $encodingType = '';
        }

        $objectList = $bucketService->listFileVersions($bucket, $prefix, $delimiter, $keyMarker, $versionMarker, $maxKeys);
        $data = [
            'ListVersionsResult' => [
                '@attributes' => ['xmlns' => 'http://s3.amazonaws.com/doc/2006-03-01/'],
                'Name' => $bucket->getName(),
                'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                'MaxKeys' => $maxKeys,
                'IsTruncated' => $objectList->isTruncated() ? 'true' : 'false',
            ],
        ];

        // add EncodingType is set
        if ($encodingType) {
            $data['ListVersionsResult']['EncodingType'] = $encodingType;
        }

        // add Delimiter if set
        if ($delimiter) {
            $data['ListVersionsResult']['Delimiter'] = ('url' == $encodingType) ? urlencode($delimiter) : $delimiter;
        }

        if ($keyMarker) {
            $data['ListVersionsResult']['KeyMarker'] = $keyMarker;
        }
        if ($versionMarker) {
            $data['ListVersionsResult']['VersionIdMarker'] = $versionMarker;
        }

        if ($objectList->isTruncated()) {
            $data['ListVersionsResult']['NextKeyMarker'] = $objectList->getNextMarker();
            $data['ListVersionsResult']['NextVersionIdMarker'] = $objectList->getNextVersionMarker();
        }

        if (count($objectList->getFiles()) > 0) {
            $data['ListVersionsResult']['#'] = [];
            foreach ($objectList->getFiles() as $file) {
                if ($file->isDeleteMarker()) {
                    $item = [
                        '@name' => 'DeleteMarker',
                        'IsLatest' => $file->isCurrentVersion() ? 'true' : 'false',
                        'Key' => ('url' == $encodingType) ? urlencode($file->getName()) : $file->getName(),
                        'LastModified' => $file->getMtime()->format('c'),
                        'VersionId' => $file->getVersion() ?? 'null',
                        'Owner' => [
                            'ID' => $bucket->getOwner()->getIdentifier(),
                        ],
                    ];
                } else {
                    $item = [
                        '@name' => 'Version',
                        'IsLatest' => $file->isCurrentVersion() ? 'true' : 'false',
                        'Key' => ('url' == $encodingType) ? urlencode($file->getName()) : $file->getName(),
                        'LastModified' => $file->getMtime()->format('c'),
                        'VersionId' => $file->getVersion() ?? 'null',
                        'ETag' => '"'.$file->getEtag().'"',
                        'Size' => $file->getSize(),
                        'StorageClass' => 'STANDARD',
                        'Owner' => [
                            'ID' => $bucket->getOwner()->getIdentifier(),
                        ],
                    ];
                }
                $data['ListVersionsResult']['#'][] = $item;
            }
        }

        if (count($objectList->getCommonPrefixes()) > 0) {
            $data['ListVersionsResult']['#CommonPrefixes'] = [];
            foreach ($objectList->getCommonPrefixes() as $prefix) {
                $data['ListVersionsResult']['#CommonPrefixes'][] = [
                    'Prefix' => ('url' == $encodingType) ? urlencode($prefix) : $prefix,
                ];
            }
        }

        return $responseService->createResponse($data);
    }
}
