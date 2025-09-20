<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\LifecycleService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class GetBucketLifecycleConfiguration extends AbstractController
{
    // Routing handled by RouteListener
    public function getBucketLifecycleConfiguration(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, LifecycleService $lifecycleService, Request $request, string $bucket): Response
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
                    ['action' => 's3:GetLifecycleConfiguration', 'resource' => $bucket->getIdentifier()],
                ],
                $bucket,
            );
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }

        if ($request->query->has('x-amz-expected-bucket-owner')) {
            if ($request->query->get('x-amz-expected-bucket-owner') !== $bucket->getOwner()->getIdentifier()) {
                return $responseService->createForbiddenResponse();
            }
        }

        try {
            $rules = [];
            foreach ($bucket->getLifecycleRules() as $rule) {
                $rules = array_merge($rules, $lifecycleService->parseLifecycleRules($rule->getRules()));
            }

            $xmlRules = $lifecycleService->parsedRulesToXmlArray($rules);

            // valid and saved
            return $responseService->createResponse(
                [
                    'LifecycleConfiguration' => [
                        '#Rule' => $xmlRules,
                    ],
                ]
            );
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketLifecycleRulesGetFailed', 'Failed to get bucket lifecycle rules.');
        }
    }
}
