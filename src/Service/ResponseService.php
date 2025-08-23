<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResponseService
{
    public function __construct(
        private GeneratorService $generatorService,
    ) {
    }

    /** @var array<string, string> */
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

    /**
     * @param mixed[] $headers
     */
    public function createResponse(mixed $data, int $status = 200, string $contentType = 'application/xml', array $headers = []): Response
    {
        // generate unique id
        $this->defaultHeaders['x-emmer-id'] = 'emr-'.$this->generatorService->generateId(32);
        if ('' !== $contentType) {
            $this->defaultHeaders['Content-Type'] = $contentType;
        }

        if (is_string($data)) {
            return new Response(
                $data,
                $status,
                array_merge(
                    $this->defaultHeaders,
                    $headers,
                )
            );
        }

        if (empty($data)) {
            return new Response(
                '',
                $status,
                array_merge(
                    $this->defaultHeaders,
                    $headers,
                )
            );
        }

        return new Response(
            $this->arrayToXmlString($data[array_key_first($data)], array_key_first($data)),
            $status,
            array_merge(
                $this->defaultHeaders,
                $headers,
            )
        );
    }

    /**
     * @param array<string> $fileParts
     * @param mixed[]       $headers
     */
    public function createFileStreamResponse(array $fileParts, int $rangeStart, int $rangeEnd, array $headers = []): Response
    {
        $this->defaultHeaders['x-emmer-id'] = 'emr-'.$this->generatorService->generateId(32);

        return new StreamedResponse(
            function () use ($fileParts, $rangeStart, $rangeEnd) {
                $outputStream = fopen('php://output', 'wb');
                $chunk = 0;
                $chunkSize = 8192;
                $remaining = -1 == $rangeStart ? -1 : $rangeEnd - $rangeStart + 1;

                foreach ($fileParts as $filePart) {
                    if (0 == $remaining) {
                        // range processed, done
                        break;
                    } elseif ($rangeStart > 0 && $rangeStart >= filesize($filePart)) {
                        // no need to process this part as start is in later part
                        $rangeStart -= filesize($filePart);
                    } elseif ($fp = fopen($filePart, 'rb')) {
                        if ($rangeStart > 0) {
                            fseek($fp, $rangeStart);
                            $rangeStart = 0;
                        }
                        while (!feof($fp)) {
                            if (0 == $remaining) {
                                break;
                            } elseif (-1 !== $remaining && $remaining < $chunkSize) {
                                $read = stream_copy_to_stream($fp, $outputStream, $remaining);
                            } else {
                                $read = stream_copy_to_stream($fp, $outputStream, $chunkSize);
                            }
                            if (false === $read) {
                                throw new \RuntimeException('Stream copy error for file: '.$filePart);
                            } else {
                                if ($remaining > 0) {
                                    $remaining -= $read;
                                }
                            }

                            ++$chunk;
                            if (0 == $chunk % 1000) {
                                flush();
                            }
                        }
                        fclose($fp);
                    } else {
                        throw new \RuntimeException('Unable to open file: '.$filePart);
                    }
                }

                fclose($outputStream);
            },
            -1 !== $rangeStart ? 206 : 200,
            array_merge(
                $this->defaultHeaders,
                $headers,
            )
        );
    }

    /**
     * @param mixed[] $array
     */
    private function arrayToXmlString(array $array, string $rootElement = 'root'): string
    {
        $xml = $this->arrayToXml($array, $rootElement);

        return $xml->asXML();
    }

    /**
     * @param mixed[] $array
     */
    private function arrayToXml(array $array, ?string $rootElement = 'root', ?\SimpleXMLElement $xml = null): \SimpleXMLElement
    {
        // create new element if not provided
        if (null === $xml) {
            $xml = new \SimpleXMLElement('<'.$rootElement.'/>');
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
                $key = 'item_'.$key;
            } else {
                $key = (string) $key;
            }

            // Handle arrays (nested elements)
            if (is_array($value)) {
                // Check if this array contains attributes
                if (isset($value['@attributes']) && is_array($value['@attributes'])) {
                    // Create child element
                    $child = $xml->addChild($key);
                    if (!$child) {
                        throw new \RuntimeException('Unable to add child element: '.$key);
                    }

                    // Add attributes
                    foreach ($value['@attributes'] as $attrKey => $attrValue) {
                        $child->addAttribute($attrKey, htmlspecialchars($attrValue));
                    }

                    // Remove attributes from value array and process remaining content
                    $contentArray = $value;
                    unset($contentArray['@attributes']);

                    // If there's a text value along with attributes
                    if (isset($contentArray['@text'])) {
                        /* @phpstan-ignore offsetAssign.valueType */
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
