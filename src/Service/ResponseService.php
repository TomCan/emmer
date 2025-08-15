<?php

namespace App\Service;

use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Response;

class ResponseService
{
    public function __construct(
        private GeneratorService $generatorService,
    )
    {
    }

    private array $defaultHeaders = [
        'x-generator' => 'Emmer',
    ];

    public function createForbiddenResponse(): Response
    {
        return $this->createErrorResponse(403, 'AccessDenied', 'Access Denied');
    }

    public function createPreconditionFailedResponse(): Response
    {
        return $this->createErrorResponse(412, 'PreconditionFailed', 'Precondition Failed');
    }

    public function createErrorResponse(int $status, string $code, string $message): Response
    {
        return $this->createResponse(
            [
                'Error' => [
                    'Code' => $code,
                    'Message' => $message,
                ],
            ],
            $status
        );
    }

    public function createNotModifiedResponse(): Response
    {
        return $this->createResponse(
            [],
            304,
            'text/plain',
        );
    }

    public function createResponse(array $data, int $status = 200, string $contentType = 'application/xml', array $headers = []): Response
    {
        // generate unique id
        $this->defaultHeaders['x-emmer-id'] = 'emr-'.$this->generatorService->generateId(32);

        if (empty($data)) {
            return new Response(
                '',
                $status,
                array_merge(
                    $this->defaultHeaders,
                    [
                        'Content-Type' => $contentType
                    ],
                    $headers,
                )
            );
        }
        return new Response(
            $this->arrayToXmlString($data[array_key_first($data)], array_key_first($data)),
            $status,
            array_merge(
                $this->defaultHeaders,
                [
                    'Content-Type' => $contentType
                ],
                $headers,
            )
        );
    }

    private function arrayToXmlString($array, $rootElement = 'root'): string
    {
        $xml = $this->arrayToXml($array, $rootElement);

        return $xml->asXML();
    }

    private function arrayToXml($array, ?string $rootElement = 'root', SimpleXMLElement $xml = null): SimpleXMLElement
    {
        // create new element if not provided
        if ($xml === null) {
            $xml = new SimpleXMLElement('<' . $rootElement . '/>');
            // check for attributes on root element
            if (isset($array['@attributes']) && is_array($array['@attributes'])) {
                // Add attributes
                foreach ($array['@attributes'] as $attrKey => $attrValue) {
                    $xml->addAttribute($attrKey, htmlspecialchars($attrValue));
                }
                unset($array['@attributes']);

                // If there's a text value along with attributes
                if (isset($array['@text'])) {
                    $xml = htmlspecialchars($array['@text']);
                    unset($array['@text']);
                }
            }
        }

        foreach ($array as $key => $value) {
            // Handle numeric keys by adding a prefix
            if (is_numeric($key)) {
                $key = 'item_' . $key;
            }

            // Handle arrays (nested elements)
            if (is_array($value)) {
                // Check if this array contains attributes
                if (isset($value['@attributes']) && is_array($value['@attributes'])) {
                    // Create child element
                    $child = $xml->addChild($key);

                    // Add attributes
                    foreach ($value['@attributes'] as $attrKey => $attrValue) {
                        $child->addAttribute($attrKey, htmlspecialchars($attrValue));
                    }

                    // Remove attributes from value array and process remaining content
                    $contentArray = $value;
                    unset($contentArray['@attributes']);

                    // If there's a text value along with attributes
                    if (isset($contentArray['@text'])) {
                        $child[0] = htmlspecialchars($contentArray['@text']);
                        unset($contentArray['@text']);
                    }

                    // Process remaining nested elements
                    if (!empty($contentArray)) {
                        $this->arrayToXml($contentArray, null, $child);
                    }
                } elseif (str_starts_with($key, '#')) {
                    // Add each element as a child of the root element, allowing for multiple elements with the same name
                    $useKey = substr($key, 1);
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $this->arrayToXml([$useKey => $item], null, $xml);
                        } else {
                            $child = $xml->addChild($useKey, htmlspecialchars($item));
                        }
                    }
                } else {
                    // Regular nested array without attributes
                    $child = $xml->addChild($key);
                    $this->arrayToXml($value, null, $child);
                }
            } else {
                // Simple value - add as child element with escaped content
                $xml->addChild($key, htmlspecialchars($value));
            }
        }

        return $xml;
    }
}
