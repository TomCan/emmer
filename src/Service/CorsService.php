<?php

namespace App\Service;

use App\Entity\Bucket;
use App\Entity\CorsRule;
use App\Exception\Cors\InvalidCorsConfigException;
use App\Exception\Cors\InvalidCorsRuleException;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;

class CorsService
{
    public function __construct(
    ) {
    }

    /**
     * @return CorsRule[]
     */
    public function parseCorsRules(string $rules): array
    {
        $xml = simplexml_load_string($rules);
        if ('CORSConfiguration' != $xml->getName()) {
            throw new InvalidCorsConfigException('CORSConfiguration element not found');
        }

        $rules = [];
        foreach ($xml->CORSRule as $rule) {
            $rules[] = $this->parseCorsRule($rule);
        }

        return $rules;
    }

    public function parseCorsRule(\SimpleXMLElement $rule): CorsRule
    {
        if (!isset($rule->AllowedMethod, $rule->AllowedOrigin)) {
            throw new InvalidCorsRuleException();
        }

        $allowedMethods = [];
        foreach ($rule->AllowedMethod as $method) {
            $method = strtoupper((string) $method);
            if (!in_array($method, ['GET', 'PUT', 'HEAD', 'POST', 'DELETE'])) {
                throw new InvalidCorsRuleException('Invalid method: '.$method);
            }
            $allowedMethods[] = $method;
        }

        $allowedOrigins = [];
        foreach ($rule->AllowedOrigin as $origin) {
            $allowedOrigins[] = (string) $origin;
        }

        $corsRule = new CorsRule(null, $allowedMethods, $allowedOrigins);

        if (isset($rule->AllowedHeader)) {
            $allowedHeaders = [];
            foreach ($rule->AllowedHeader as $header) {
                $allowedHeaders[] = (string) $header;
            }
            $corsRule->setAllowedHeaders($allowedHeaders);
        } else {
            $corsRule->setAllowedHeaders(null);
        }

        if (isset($rule->ExposeHeader)) {
            $exposeHeaders = [];
            foreach ($rule->ExposeHeader as $header) {
                $exposeHeaders[] = (string) $header;
            }
            $corsRule->setExposeHeaders($exposeHeaders);
        } else {
            $corsRule->setExposeHeaders(null);
        }

        if (isset($rule->MaxAgeSeconds)) {
            $seconds = (int) $rule->MaxAgeSeconds;
            if ($seconds < 0) {
                throw new InvalidCorsRuleException('MaxAgeSeconds must be a positive number');
            }
            $corsRule->setMaxAgeSeconds($seconds);
        } else {
            $corsRule->setMaxAgeSeconds(null);
        }

        if (isset($rule->ID)) {
            $id = trim((string) $rule->ID);
            if (strlen($id) < 1 || strlen($id) > 255) {
                throw new InvalidCorsRuleException('ID must be between 1 and 255 characters');
            }
            $corsRule->setCustomId($id);
        } else {
            $corsRule->setCustomId(null);
        }

        return $corsRule;
    }

    /**
     * @param CorsRule[] $rules
     *
     * @return mixed[]
     */
    public function convertRulesToXmlArray(array $rules): array
    {
        $convertedRules = [
            'CORSConfiguration' => [
                '#CORSRule' => [],
            ],
        ];

        foreach ($rules as $rule) {
            $corsRule = [
                '#AllowedMethod' => $rule->getAllowedMethods(),
                '#AllowedOrigin' => $rule->getAllowedOrigins(),
            ];

            if (null !== $rule->getAllowedHeaders()) {
                $corsRule['#AllowedHeader'] = $rule->getAllowedHeaders();
            }

            if (null !== $rule->getExposeHeaders()) {
                $corsRule['#ExposeHeader'] = $rule->getExposeHeaders();
            }

            if (null !== $rule->getMaxAgeSeconds()) {
                $corsRule['MaxAgeSeconds'] = $rule->getMaxAgeSeconds();
            }

            if (null !== $rule->getCustomId()) {
                $corsRule['ID'] = $rule->getCustomId();
            }

            $convertedRules['CORSConfiguration']['#CORSRule'][] = $corsRule;
        }

        return $convertedRules;
    }

    public function getMatchingCorsRule(Bucket $bucket, HeaderBag $headers): ?CorsRule
    {
        $origin = $headers->get('Origin');
        $method = $headers->get('Access-Control-Request-Method');

        if (null === $origin || null === $method) {
            // required headers missing
            return null;
        }

        foreach ($bucket->getCorsRules() as $rule) {
            $originMatches = in_array($origin, $rule->getAllowedOrigins()) || in_array('*', $rule->getAllowedOrigins());
            $methodMatches = in_array($method, $rule->getAllowedMethods());
            if (!$originMatches && !$methodMatches) {
                continue;
            }

            // if Access-Control-Request-Headers is passed, all headers must be included in rule to match
            if ($headers->get('Access-Control-Request-Headers')) {
                if (is_array($rule->getAllowedHeaders())) {
                    $requestHeaders = explode(',', $headers->get('Access-Control-Request-Headers'));
                    foreach ($requestHeaders as $requestHeader) {
                        $requestHeader = strtolower(trim($requestHeader));
                        foreach ($rule->getAllowedHeaders() as $allowedHeader) {
                            $allowedHeader = strtolower(trim($allowedHeader));
                            if ($allowedHeader === $requestHeader) {
                                // header matches
                                continue 2; // foreach $requestHeaders
                            }
                        }
                        // if we get here, header didn't match so rule doesn't match
                        continue 2; // foreach $bucket->getCorsRules
                    }

                    // if we get here, all headers matched, we have a matching rule
                    return $rule;
                }
            }
        }

        // if we get here, no rule matched
        return null;
    }
}
