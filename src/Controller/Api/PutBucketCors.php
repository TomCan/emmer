<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\Cors\InvalidCorsConfigException;
use App\Exception\Policy\InvalidPolicyException;
use App\Service\AuthorizationService;
use App\Service\BucketService;
use App\Service\CorsService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class PutBucketCors extends AbstractController
{
    // Routing handled by RouteListener
    public function putBucketCors(AuthorizationService $authorizationService, ResponseService $responseService, BucketService $bucketService, CorsService $corsService, Request $request, string $bucket): Response
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
                    ['action' => 's3:PutBucketCORS', 'resource' => $bucket->getIdentifier()],
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
            $rulesString = $request->getContent();
            $rules = $corsService->parseCorsRules($rulesString);
            $bucketService->setBucketCors($bucket, $rules, true);

            return $responseService->createResponse(
                [],
                204,
                'text/plain'
            );
        } catch (InvalidCorsConfigException $e) {
            return $responseService->createErrorResponse(400, 'InvalidCorsConfiguration', 'Invalid cors configurations.');
        } catch (InvalidPolicyException $e) {
            return $responseService->createErrorResponse(400, 'InvalidCorsRule', 'Invalid cors rule.');
        } catch (\Exception $e) {
            return $responseService->createErrorResponse(400, 'BucketCorsPutFailed', 'Failed to put bucket cors configuration.');
        }
    }
}
