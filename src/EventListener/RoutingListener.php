<?php

namespace App\EventListener;

use App\Controller\Api\DeleteObjects;
use App\Controller\Api\PutObject;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RoutingListener implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only handle our specific route pattern and methods
        $requestString = $request->getMethod().' '.$request->getPathInfo();
        $matches = [];

        // match POST /{bucket}
        if (preg_match('#^POST /([^/]+)/?$#', $requestString, $matches)) {
            if ($request->query->has('delete')) {
                $request->attributes->set('_controller', DeleteObjects::class.'::deleteObjects');
                $request->attributes->set('bucket', $matches[1]);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64], // Priority 10
        ];
    }
}
