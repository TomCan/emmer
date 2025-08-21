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

class ListBuckets extends AbstractController
{
    #[Route('/', name: 'list_buckets', methods: ['GET'])]
    public function listBuckets(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, Request $request): Response
    {
        /** @var ?User $user */
        $user = $this->getUser();
        try {
            $authorizationService->requireAll(
                $user,
                [
                    ['action' => 's3:ListAllMyBuckets', 'resource' => 'emr:bucket:'.$request->query->getString('prefix', '')],
                ],
                $user,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        $marker = $request->query->getString('continuation-token', '');
        $prefix = $request->query->getString('prefix', '');
        $maxBuckets = $request->query->getInt('max-buckets', 100);
        if ($maxBuckets < 1 || $maxBuckets > 10000) {
            return $responseService->createErrorResponse(400, 'InvalidRequest', 'The specified max-buckets is not valid.');
        }

        $bucketList = $bucketService->listOwnBuckets($user, $prefix, $marker, $maxBuckets);
        $data = [
            'ListAllMyBucketsResult' => [
                'Buckets' => ['#Bucket' => []],
            ],
        ];

        // add prefix if set
        if ($prefix) {
            $data['ListAllMyBucketsResult']['Prefix'] = $prefix;
        }

        if ($bucketList->isTruncated()) {
            $data['ListAllMyBucketsResult']['ContinuationToken'] = $bucketList->getNextMarker();
        }

        if (count($bucketList->getBuckets()) > 0) {
            foreach ($bucketList->getBuckets() as $bucket) {
                $item = [
                    'BucketArn' => $bucket->getIdentifier(),
                    'BucketRegion' => 'default',
                    'CreationDate' => $bucket->getCtime()->format('c'),
                    'Name' => $bucket->getName(),
                    'Owner' => [
                        'ID' => $bucket->getOwner()->getIdentifier(),
                    ],
                ];
                $data['ListAllMyBucketsResult']['Buckets']['#Bucket'][] = $item;
            }
        }

        return $responseService->createResponse($data);
    }
}
