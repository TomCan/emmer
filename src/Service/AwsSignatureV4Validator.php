<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class AwsSignatureV4Validator
{
    private const ALGORITHM = 'AWS4-HMAC-SHA256';
    private const AWS_REQUEST = 'aws4_request';

    public function extractAccessKey(Request $request): ?string
    {
        $header = $request->headers->get('authorization', '');
        if (!$header) {
            return null;
        }

        $parts = $this->parseAuthorizationHeader($header);
        if (!$parts) {
            return null;
        }

        $credential = explode('/', $parts['credential']);

        return $credential[0];
    }

    /**
     * Validates an AWS Signature V4 signed request.
     */
    public function validateRequest(Request $request, string $region, string $service, string $secret): void
    {
        try {
            $authHeader = $request->headers->get('authorization');
            if (!$authHeader) {
                throw new AuthenticationException('Authorization header is missing.');
            }

            $authComponents = $this->parseAuthorizationHeader($authHeader);
            if (!$authComponents) {
                throw new AuthenticationException('Authorization header invalid.');
            }

            // Extract required headers
            $timestamp = $request->headers->get('x-amz-date');
            $contentSha256 = $request->headers->get('x-amz-content-sha256');

            if (!$timestamp || !$contentSha256) {
                throw new AuthenticationException('Required headers are missing.');
            }

            // Validate timestamp (optional: check if request is not too old)
            if (!$this->isValidTimestamp($timestamp)) {
                throw new AuthenticationException('Invalid timestamp.');
            }

            // parse credential component
            $credential = explode('/', $authComponents['credential']);
            if (5 !== count($credential) || 'aws4_request' !== $credential[4]) {
                throw new AuthenticationException('Invalid credential format.');
            }
            // check region and service
            if ('' !== $region && $region !== $credential[2]) {
                throw new AuthenticationException('Region does not match.');
            }
            if ($service !== $credential[3]) {
                throw new AuthenticationException('Service does not match.');
            }
            $accessKey = $credential[0];

            // Generate our own signature
            $calculatedSignature = $this->calculateSignature(
                $request,
                $authComponents['credential'],
                $secret,
                $authComponents['signedHeaders'],
                $timestamp,
                $contentSha256
            );

            // Compare signatures
            if (!hash_equals($calculatedSignature, $authComponents['signature'])) {
                throw new AuthenticationException('Invalid signature.');
            }
        } catch (AuthenticationException $e) {
            // throw up
            throw $e;
        } catch (\Exception $e) {
            // not sure what went wrong
            throw new \Exception('Unexpected exception: '.$e->getMessage());
        }
    }

    /**
     * Parse the Authorization header.
     * Extract Credential, SignedHeaders, and Signature.
     *
     * @return array<string,string>|null
     */
    private function parseAuthorizationHeader(string $authHeader): ?array
    {
        if (!str_starts_with($authHeader, self::ALGORITHM)) {
            return null;
        }

        $pattern = '/^'.preg_quote(self::ALGORITHM).' Credential=([^,]+), SignedHeaders=([^,]+), Signature=([a-f0-9]+)$/';

        if (!preg_match($pattern, $authHeader, $matches)) {
            return null;
        }

        return [
            'credential' => $matches[1],
            'signedHeaders' => $matches[2],
            'signature' => $matches[3],
        ];
    }

    /**
     * Calculate the expected signature.
     */
    private function calculateSignature(
        Request $request,
        string $credential,
        string $secret,
        string $signedHeaders,
        string $timestamp,
        string $contentSha256,
    ): string {
        // Parse credential to get date and region
        $credentialParts = explode('/', $credential);
        if (count($credentialParts) < 4) {
            throw new \InvalidArgumentException('Invalid credential format');
        }

        $date = $credentialParts[1];
        $region = $credentialParts[2];
        $service = $credentialParts[3];

        // Step 1: Create canonical request
        $canonicalRequest = $this->createCanonicalRequest(
            $request,
            $signedHeaders,
            $contentSha256
        );

        // Step 2: Create string to sign
        $stringToSign = $this->createStringToSign(
            $timestamp,
            $date,
            $region,
            $service,
            $canonicalRequest
        );

        // Step 3: Calculate signature
        return $this->calculateSignatureFromStringToSign(
            $secret,
            $stringToSign,
            $date,
            $region,
            $service
        );
    }

    /**
     * Create canonical request string.
     */
    private function createCanonicalRequest(
        Request $request,
        string $signedHeaders,
        string $contentSha256,
    ): string {
        $method = strtoupper($request->getMethod());
        $path = $this->getCanonicalUri($request);
        $queryString = $this->getCanonicalQueryString($request);
        $headers = $this->getCanonicalHeaders($request, $signedHeaders);

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            $queryString,
            $headers,
            $signedHeaders,
            $contentSha256,
        ]);

        return hash('sha256', $canonicalRequest);
    }

    /**
     * Get canonical URI.
     */
    private function getCanonicalUri(Request $request): string
    {
        $path = $request->getPathInfo();

        // Encode each path segment
        $segments = explode('/', $path);
        $encodedSegments = array_map(function ($segment) {
            return rawurlencode($segment);
        }, $segments);

        return implode('/', $encodedSegments);
    }

    /**
     * Get canonical query string.
     */
    private function getCanonicalQueryString(Request $request): string
    {
        $queryParams = $request->query->all();

        if (empty($queryParams)) {
            return '';
        }

        $encodedParams = [];
        foreach ($queryParams as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $encodedParams[] = rawurlencode($key).'='.rawurlencode($v);
                }
            } else {
                $encodedParams[] = rawurlencode($key).'='.rawurlencode($value);
            }
        }

        sort($encodedParams);

        return implode('&', $encodedParams);
    }

    /**
     * Get canonical headers.
     */
    private function getCanonicalHeaders(Request $request, string $signedHeaders): string
    {
        $headerNames = array_map('trim', explode(';', $signedHeaders));
        $canonicalHeaders = [];

        foreach ($headerNames as $headerName) {
            $headerValue = $request->headers->get($headerName);
            if (null !== $headerValue) {
                // Normalize header value (trim and collapse whitespace)
                $normalizedValue = preg_replace('/\s+/', ' ', trim($headerValue));
                $canonicalHeaders[] = strtolower($headerName).':'.$normalizedValue;
            }
        }

        return implode("\n", $canonicalHeaders)."\n";
    }

    /**
     * Create string to sign.
     */
    private function createStringToSign(
        string $timestamp,
        string $date,
        string $region,
        string $service,
        string $canonicalRequestHash,
    ): string {
        $credentialScope = implode('/', [$date, $region, $service, self::AWS_REQUEST]);

        return implode("\n", [
            self::ALGORITHM,
            $timestamp,
            $credentialScope,
            $canonicalRequestHash,
        ]);
    }

    /**
     * Calculate signature from string to sign.
     */
    private function calculateSignatureFromStringToSign(
        string $secret,
        string $stringToSign,
        string $date,
        string $region,
        string $service,
    ): string {
        $kDate = hash_hmac('sha256', $date, 'AWS4'.$secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', self::AWS_REQUEST, $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning);
    }

    /**
     * Validate timestamp (optional security check).
     */
    private function isValidTimestamp(string $timestamp): bool
    {
        try {
            $requestTime = \DateTime::createFromFormat('Ymd\THis\Z', $timestamp);
            if (!$requestTime) {
                return false;
            }

            $now = new \DateTime('now', new \DateTimeZone('UTC'));
            $diff = abs($now->getTimestamp() - $requestTime->getTimestamp());

            // Allow 15 minutes of clock skew
            return $diff <= 900;
        } catch (\Exception $e) {
            return false;
        }
    }
}
