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
        // Try header-based authentication first
        $header = $request->headers->get('authorization', '');
        if ($header) {
            $parts = $this->parseAuthorizationHeader($header);
            if ($parts) {
                $credential = explode('/', $parts['credential']);

                return $credential[0];
            }
        }

        // Try URL-based authentication
        $credential = $request->query->get('X-Amz-Credential');
        if ($credential) {
            $credentialParts = explode('/', $credential);

            return $credentialParts[0] ?: null;
        }

        return null;
    }

    /**
     * Validates an AWS Signature V4 signed request (supports both header and URL-based auth).
     */
    public function validateRequest(Request $request, string $region, string $service, string $secret): void
    {
        try {
            // Determine authentication method
            $authHeader = $request->headers->get('authorization');
            $isUrlAuth = !$authHeader && $request->query->has('X-Amz-Signature');

            if (!$authHeader && !$isUrlAuth) {
                throw new AuthenticationException('Authorization header or query parameters are missing.');
            }

            if ($isUrlAuth) {
                $this->validateUrlBasedRequest($request, $region, $service, $secret);
            } else {
                $this->validateHeaderBasedRequest($request, $region, $service, $secret);
            }
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new \Exception('Unexpected exception: '.$e->getMessage());
        }
    }

    /**
     * Validates header-based authentication (original implementation).
     */
    private function validateHeaderBasedRequest(Request $request, string $region, string $service, string $secret): void
    {
        $authHeader = $request->headers->get('authorization');
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

        // Validate timestamp
        if (!$this->isValidTimestamp($timestamp, true)) {
            throw new AuthenticationException('Invalid timestamp.');
        }

        // Parse credential component
        $credential = explode('/', $authComponents['credential']);
        if (5 !== count($credential) || 'aws4_request' !== $credential[4]) {
            throw new AuthenticationException('Invalid credential format.');
        }

        // Check region and service
        if ('' !== $region && $region !== $credential[2]) {
            throw new AuthenticationException('Region does not match.');
        }
        if ($service !== $credential[3]) {
            throw new AuthenticationException('Service does not match.');
        }

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
    }

    /**
     * Validates URL-based authentication.
     */
    private function validateUrlBasedRequest(Request $request, string $region, string $service, string $secret): void
    {
        // Extract required query parameters
        $algorithm = $request->query->get('X-Amz-Algorithm');
        $credential = $request->query->get('X-Amz-Credential');
        $date = $request->query->get('X-Amz-Date');
        $expires = $request->query->get('X-Amz-Expires');
        $signedHeaders = $request->query->get('X-Amz-SignedHeaders');
        $signature = $request->query->get('X-Amz-Signature');

        if (!$algorithm || !$credential || !$date || !$expires || !$signedHeaders || !$signature) {
            throw new AuthenticationException('Required query parameters are missing.');
        }

        // Validate algorithm
        if (self::ALGORITHM !== $algorithm) {
            throw new AuthenticationException('Invalid algorithm.');
        }

        // Validate timestamp
        if (!$this->isValidTimestamp($date, false)) {
            throw new AuthenticationException('Invalid timestamp.');
        }

        // Validate expiration
        if (!$this->isValidExpiration($date, $expires)) {
            throw new AuthenticationException('Request has expired.');
        }

        // Parse credential component
        $credentialParts = explode('/', $credential);
        if (5 !== count($credentialParts) || 'aws4_request' !== $credentialParts[4]) {
            throw new AuthenticationException('Invalid credential format.');
        }

        // Check region and service
        if ('' !== $region && $region !== $credentialParts[2]) {
            throw new AuthenticationException('Region does not match.');
        }
        if ($service !== $credentialParts[3]) {
            throw new AuthenticationException('Service does not match.');
        }

        // For URL-based auth, content SHA256 is typically UNSIGNED-PAYLOAD
        $contentSha256 = 'UNSIGNED-PAYLOAD';

        // Generate our own signature
        $calculatedSignature = $this->calculateSignatureForUrl(
            $request,
            $credential,
            $secret,
            $signedHeaders,
            $date,
            $expires,
            $contentSha256
        );

        // Compare signatures
        if (!hash_equals($calculatedSignature, $signature)) {
            throw new AuthenticationException('Invalid signature.');
        }
    }

    /**
     * Calculate signature for URL-based authentication.
     */
    private function calculateSignatureForUrl(
        Request $request,
        string $credential,
        string $secret,
        string $signedHeaders,
        string $timestamp,
        string $expires,
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

        // Step 1: Create canonical request for URL-based auth
        $canonicalRequest = $this->createCanonicalRequestForUrl(
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
     * Create canonical request for URL-based authentication.
     */
    private function createCanonicalRequestForUrl(
        Request $request,
        string $signedHeaders,
        string $contentSha256,
    ): string {
        $method = strtoupper($request->getMethod());
        $path = $request->getPathInfo();
        $queryString = $this->getCanonicalQueryStringForUrl($request);
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
     * Get canonical query string for URL-based auth (excludes X-Amz-Signature).
     */
    private function getCanonicalQueryStringForUrl(Request $request): string
    {
        $queryParams = $request->query->all();

        // Remove the signature parameter from the query string
        unset($queryParams['X-Amz-Signature']);

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
     * Validate expiration for URL-based authentication.
     */
    private function isValidExpiration(string $timestamp, string $expires): bool
    {
        try {
            $requestTime = \DateTime::createFromFormat('Ymd\THis\Z', $timestamp, new \DateTimeZone('UTC'));
            if (!$requestTime) {
                return false;
            }

            $expirationTime = clone $requestTime;
            $expirationTime->add(new \DateInterval('PT'.$expires.'S'));

            $now = new \DateTime('now', new \DateTimeZone('UTC'));

            return $now <= $expirationTime;
        } catch (\Exception $e) {
            return false;
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
        $path = $request->getPathInfo();
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
    private function isValidTimestamp(string $timestamp, bool $skewCheck = true): bool
    {
        try {
            // Explicitly create the DateTime object in UTC timezone
            $requestTime = \DateTime::createFromFormat('Ymd\THis\Z', $timestamp, new \DateTimeZone('UTC'));
            if (!$requestTime) {
                return false;
            }

            if ($skewCheck) {
                $now = new \DateTime('now', new \DateTimeZone('UTC'));
                $diff = abs($now->getTimestamp() - $requestTime->getTimestamp());

                // Allow 5 minutes of clock skew
                return $diff <= 300;
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
