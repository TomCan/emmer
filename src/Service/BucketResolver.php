<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class BucketResolver
{
    private string $routingMode;

    public function __construct(string $routingMode)
    {
        $this->routingMode = $routingMode;
    }

    /**
     * @return array<string, string>
     */
    public function getFromRequest(Request $request): array
    {
        if ('host' === $this->routingMode) {
            // bucketname is first part of host
            $host = $request->getHost();
            $parts = explode('.', $host);
            $bucket = $parts[0];
            $path = $request->getPathInfo();
        } else {
            // bucketname is first part of path
            $parts = explode('/', $request->getPathInfo(), 2);
            $bucket = $parts[0];
            $path = $parts[1];
        }

        return ['bucket' => $bucket, 'path' => $path];
    }
}
