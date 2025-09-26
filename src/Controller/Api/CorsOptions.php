<?php

namespace App\Controller\Api;

use App\Service\BucketService;
use App\Service\CorsService;
use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CorsOptions extends AbstractController
{
    #[Route('/{bucket}', name: 'cors_options_bucket', methods: ['OPTIONS'])]
    #[Route('/{bucket}/{key}', name: 'cors_options_bucket_key', methods: ['OPTIONS'], requirements: ['key' => '.*'])]
    public function corsOptions(ResponseService $responseService, BucketService $bucketService, CorsService $corsService, Request $request, string $bucket, string $key = ''): Response
    {
        $bucket = $bucketService->getBucket($bucket);
        if (!$bucket) {
            return $responseService->createForbiddenResponse();
        }

        try {
            if (null == $bucket->getCorsRules() || $bucket->getCorsRules()->isEmpty()) {
                return $responseService->createForbiddenResponse();
            } else {
                $rule = $corsService->getMatchingCorsRule($bucket, $request->headers);
                if (null === $rule) {
                    return $responseService->createForbiddenResponse();
                } else {
                    $headers = [
                        'Access-Control-Allow-Origin' => $request->headers->get('Origin'),
                        'Access-Control-Allow-Methods' => implode(', ', $rule->getAllowedMethods()),
                    ];
                    if ($rule->getAllowedMethods()) {
                        $headers['Access-Control-Allow-Headers'] = strtolower(implode(', ', $rule->getAllowedHeaders()));
                    }

                    return $responseService->createResponse(
                        '',
                        204,
                        '',
                        $headers,
                    );
                }
            }
        } catch (AccessDeniedException $e) {
            return $responseService->createForbiddenResponse();
        }
    }
}
