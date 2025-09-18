<?php

namespace App\EventListener;

use App\Controller\Api\AbortMultipartUpload;
use App\Controller\Api\CompleteMultipartUpload;
use App\Controller\Api\CreateMultipartUpload;
use App\Controller\Api\DeleteBucketLifecycle;
use App\Controller\Api\DeleteBucketPolicy;
use App\Controller\Api\DeleteObjects;
use App\Controller\Api\GetBucketLifecycleConfiguration;
use App\Controller\Api\GetBucketPolicy;
use App\Controller\Api\GetBucketVersioning;
use App\Controller\Api\ListMultipartUploads;
use App\Controller\Api\ListObjectVersions;
use App\Controller\Api\ListParts;
use App\Controller\Api\PutBucketLifecycleConfiguration;
use App\Controller\Api\PutBucketPolicy;
use App\Controller\Api\PutBucketVersioning;
use App\Controller\Api\UploadPart;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RoutingListener implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        /**
         * Some routes need to be matched to a different controller/action if specific query parameters are present.
         * This can't be done / is very hard to do with the default router.
         */
        $request = $event->getRequest();
        $requestString = $request->getMethod().' '.$request->getPathInfo();
        $matches = [];

        // match GET /{bucket}
        if (preg_match('#^GET /([^/]+)/?$#', $requestString, $matches)) {
            if ($request->query->has('policy')) {
                $request->attributes->set('_controller', GetBucketPolicy::class.'::getBucketPolicy');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('lifecycle')) {
                $request->attributes->set('_controller', GetBucketLifecycleConfiguration::class.'::getBucketLifecycleConfiguration');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('versioning')) {
                $request->attributes->set('_controller', GetBucketVersioning::class.'::getBucketVersioning');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('versions')) {
                $request->attributes->set('_controller', ListObjectVersions::class.'::listObjectVersions');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('uploads')) {
                $request->attributes->set('_controller', ListMultipartUploads::class.'::listMultipartUploads');
                $request->attributes->set('bucket', $matches[1]);
            }
        }

        // match POST /{bucket}
        if (preg_match('#^POST /([^/]+)/?$#', $requestString, $matches)) {
            if ($request->query->has('delete')) {
                $request->attributes->set('_controller', DeleteObjects::class.'::deleteObjects');
                $request->attributes->set('bucket', $matches[1]);
            }
        }

        // match PUT /{bucket}
        if (preg_match('#^PUT /([^/]+)/?$#', $requestString, $matches)) {
            if ($request->query->has('policy')) {
                $request->attributes->set('_controller', PutBucketPolicy::class.'::putBucketPolicy');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('versioning')) {
                $request->attributes->set('_controller', PutBucketVersioning::class.'::putBucketVersioning');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('lifecycle')) {
                $request->attributes->set('_controller', PutBucketLifecycleConfiguration::class.'::putBucketLifecycleConfiguration');
                $request->attributes->set('bucket', $matches[1]);
            }
        }

        // match DELETE /{bucket}
        if (preg_match('#^DELETE /([^/]+)/?$#', $requestString, $matches)) {
            if ($request->query->has('policy')) {
                $request->attributes->set('_controller', DeleteBucketPolicy::class.'::deleteBucketPolicy');
                $request->attributes->set('bucket', $matches[1]);
            } elseif ($request->query->has('lifecycle')) {
                $request->attributes->set('_controller', DeleteBucketLifecycle::class.'::deleteBucketLifecycle');
                $request->attributes->set('bucket', $matches[1]);
            }
        }

        // match GET /{bucket}/{key}
        if (preg_match('#^GET /([^/]+)/(.+)$#', $requestString, $matches)) {
            if ($request->query->has('uploadId')) {
                // Multipart upload: switch to CreateMultipartUpload controller
                $request->attributes->set('_controller', ListParts::class.'::listParts');
                $request->attributes->set('bucket', $matches[1]);
                $request->attributes->set('key', $matches[2]);
                $request->attributes->set('uploadId', trim($request->query->getString('uploadId')));
            }
        }

        // match POST /{bucket}/{key}
        if (preg_match('#^POST /([^/]+)/(.+)$#', $requestString, $matches)) {
            if ($request->query->has('uploads')) {
                // Multipart upload: switch to CreateMultipartUpload controller
                $request->attributes->set('_controller', CreateMultipartUpload::class.'::createMultipartUpload');
                $request->attributes->set('bucket', $matches[1]);
                $request->attributes->set('key', $matches[2]);
            } elseif ($request->query->has('uploadId')) {
                // Multipart upload: switch to CompleteMultipartUpload controller
                $request->attributes->set('_controller', CompleteMultipartUpload::class.'::completeMultipartUpload');
                $request->attributes->set('bucket', $matches[1]);
                $request->attributes->set('key', $matches[2]);
                $request->attributes->set('uploadId', $request->query->get('uploadId'));
            }
        }

        // match PUT /{bucket}/{key}
        if (preg_match('#^PUT /([^/]+)/(.+)$#', $requestString, $matches)) {
            if ($request->query->has('uploadId') && $request->query->has('partNumber')) {
                // Multipart upload: switch to UploadPart controller
                $request->attributes->set('_controller', UploadPart::class.'::uploadPart');
                $request->attributes->set('bucket', $matches[1]);
                $request->attributes->set('key', $matches[2]);
                $request->attributes->set('uploadId', $request->query->get('uploadId'));
                $request->attributes->set('partNumber', $request->query->getInt('partNumber'));
            }
        }

        // match DELETE /{bucket}/{key}
        if (preg_match('#^DELETE /([^/]+)/(.+)$#', $requestString, $matches)) {
            if ($request->query->has('uploadId')) {
                // Multipart upload: switch to AbortMultipartUpload controller
                $request->attributes->set('_controller', AbortMultipartUpload::class.'::abortMultipartUpload');
                $request->attributes->set('bucket', $matches[1]);
                $request->attributes->set('key', $matches[2]);
                $request->attributes->set('uploadId', $request->query->get('uploadId'));
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64], // Priority 64, higher than 32 of the default router
        ];
    }
}
