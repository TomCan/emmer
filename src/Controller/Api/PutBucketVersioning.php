<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Bucket\InvalidVersioningConfigException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\PolicyService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PutBucketVersioning extends AbstractController
{
    // Routing handled by RouteListener
    public function putBucketVersioning(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, PolicyService $policyService, Request $request, string $bucket): Response
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
                    ['action' => 's3:PutBucketVersioning', 'resource' => $bucket->getIdentifier()],
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
            $versioningConfig = new \SimpleXMLElement($request->getContent());
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'MalformedXML', 'Malformed XML');
        }

        try {
            $bucketService->setBucketVersioning($bucket, $versioningConfig, true);

            return $responseService->createResponse(
                [],
                204,
                'text/plain'
            );
        } catch (InvalidVersioningConfigException $e) {
            return $responseService->createErrorResponse(400, 'InvalidVersioningConfiguration', 'Invalid versioning configuration.');
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketVersioningConfigurationPutFailed', 'Failed to put bucket versioning configuration.');
        }
    }
}
