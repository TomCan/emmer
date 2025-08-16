<?php

namespace App\Controller\Api;

use App\Service\ResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BucketRootController extends AbstractController
{
    #[Route('/{bucket}', name: 'bucket_root_proxy_post', methods: ['POST'])]
    public function resolvePost(ResponseService $responseService, Request $request, string $bucket): Response
    {
        /**
         * pseudo controller to overcome not being able to route based on query parameters
         */

        // DeleteObjects API
        if ($request->query->has('delete')) {
            return $this->forward(
                DeleteObjects::class.'::deleteObjects',
                ['request' => $request, 'bucket' => $bucket]
            );
        }

        // No match found
        return $responseService->createErrorResponse(400, 'InvalidRequest', 'Invalid Request');
    }
}
