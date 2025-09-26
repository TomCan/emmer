<?php

namespace App\Tests\Service\CorsService;

use App\Entity\CorsRule;
use App\Exception\Cors\InvalidCorsConfigException;
use App\Exception\Cors\InvalidCorsRuleException;
use App\Service\CorsService;
use PHPUnit\Framework\TestCase;

class CorsServiceTest extends TestCase
{
    private CorsService $corsService;

    protected function setUp(): void
    {
        $this->corsService = new CorsService();
    }

    public function testParseCorsRulesValidXml(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <CORSConfiguration>
            <CORSRule>
                <ID>rule1</ID>
                <AllowedMethod>GET</AllowedMethod>
                <AllowedMethod>POST</AllowedMethod>
                <AllowedOrigin>*</AllowedOrigin>
                <AllowedHeader>Content-Type</AllowedHeader>
                <ExposeHeader>ETag</ExposeHeader>
                <MaxAgeSeconds>3600</MaxAgeSeconds>
            </CORSRule>
            <CORSRule>
                <AllowedMethod>DELETE</AllowedMethod>
                <AllowedOrigin>https://example.com</AllowedOrigin>
            </CORSRule>
        </CORSConfiguration>';

        $rules = $this->corsService->parseCorsRules($xml);

        $this->assertCount(2, $rules);

        // Test first rule
        $this->assertInstanceOf(CorsRule::class, $rules[0]);
        $this->assertEquals(['GET', 'POST'], $rules[0]->getAllowedMethods());
        $this->assertEquals(['*'], $rules[0]->getAllowedOrigins());
        $this->assertEquals(['Content-Type'], $rules[0]->getAllowedHeaders());
        $this->assertEquals(['ETag'], $rules[0]->getExposeHeaders());
        $this->assertEquals(3600, $rules[0]->getMaxAgeSeconds());
        $this->assertEquals('rule1', $rules[0]->getCustomId());

        // Test second rule
        $this->assertEquals(['DELETE'], $rules[1]->getAllowedMethods());
        $this->assertEquals(['https://example.com'], $rules[1]->getAllowedOrigins());
        $this->assertNull($rules[1]->getAllowedHeaders());
        $this->assertNull($rules[1]->getExposeHeaders());
        $this->assertNull($rules[1]->getMaxAgeSeconds());
        $this->assertNull($rules[1]->getCustomId());
    }

    public function testParseCorsRulesInvalidRootElement(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <InvalidRoot>
            <CORSRule>
                <AllowedMethod>GET</AllowedMethod>
                <AllowedOrigin>*</AllowedOrigin>
            </CORSRule>
        </InvalidRoot>';

        $this->expectException(InvalidCorsConfigException::class);
        $this->expectExceptionMessage('CORSConfiguration element not found');

        $this->corsService->parseCorsRules($xml);
    }

    public function testParseCorsRuleValidRule(): void
    {
        $xmlString = '<CORSRule>
            <ID>test-rule</ID>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedMethod>POST</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
            <AllowedOrigin>https://example.com</AllowedOrigin>
            <AllowedHeader>Content-Type</AllowedHeader>
            <AllowedHeader>Authorization</AllowedHeader>
            <ExposeHeader>ETag</ExposeHeader>
            <MaxAgeSeconds>3600</MaxAgeSeconds>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);
        $rule = $this->corsService->parseCorsRule($xmlElement);

        $this->assertInstanceOf(CorsRule::class, $rule);
        $this->assertEquals(['GET', 'POST'], $rule->getAllowedMethods());
        $this->assertEquals(['*', 'https://example.com'], $rule->getAllowedOrigins());
        $this->assertEquals(['Content-Type', 'Authorization'], $rule->getAllowedHeaders());
        $this->assertEquals(['ETag'], $rule->getExposeHeaders());
        $this->assertEquals(3600, $rule->getMaxAgeSeconds());
        $this->assertEquals('test-rule', $rule->getCustomId());
    }

    public function testParseCorsRuleMinimalValidRule(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);
        $rule = $this->corsService->parseCorsRule($xmlElement);

        $this->assertEquals(['GET'], $rule->getAllowedMethods());
        $this->assertEquals(['*'], $rule->getAllowedOrigins());
        $this->assertNull($rule->getAllowedHeaders());
        $this->assertNull($rule->getExposeHeaders());
        $this->assertNull($rule->getMaxAgeSeconds());
        $this->assertNull($rule->getCustomId());
    }

    public function testParseCorsRuleMissingAllowedMethod(): void
    {
        $xmlString = '<CORSRule>
            <AllowedOrigin>*</AllowedOrigin>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testParseCorsRuleMissingAllowedOrigin(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testParseCorsRuleInvalidMethod(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>INVALID</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->expectExceptionMessage('Invalid method: INVALID');
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testParseCorsRuleMethodCaseInsensitive(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>get</AllowedMethod>
            <AllowedMethod>post</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);
        $rule = $this->corsService->parseCorsRule($xmlElement);

        $this->assertEquals(['GET', 'POST'], $rule->getAllowedMethods());
    }

    public function testParseCorsRuleNegativeMaxAgeSeconds(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
            <MaxAgeSeconds>-1</MaxAgeSeconds>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->expectExceptionMessage('MaxAgeSeconds must be a positive number');
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testParseCorsRuleZeroMaxAgeSeconds(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
            <MaxAgeSeconds>0</MaxAgeSeconds>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);
        $rule = $this->corsService->parseCorsRule($xmlElement);

        $this->assertEquals(0, $rule->getMaxAgeSeconds());
    }

    public function testParseCorsRuleIdTooShort(): void
    {
        $xmlString = '<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
            <ID></ID>
        </CORSRule>';

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->expectExceptionMessage('ID must be between 1 and 255 characters');
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testParseCorsRuleIdTooLong(): void
    {
        $longId = str_repeat('a', 256);
        $xmlString = "<CORSRule>
            <AllowedMethod>GET</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
            <ID>$longId</ID>
        </CORSRule>";

        $xmlElement = simplexml_load_string($xmlString);

        $this->expectException(InvalidCorsRuleException::class);
        $this->expectExceptionMessage('ID must be between 1 and 255 characters');
        $this->corsService->parseCorsRule($xmlElement);
    }

    public function testConvertRulesToXmlArraySingleRule(): void
    {
        $corsRule = new CorsRule(null, ['GET', 'POST'], ['*']);
        $corsRule->setAllowedHeaders(['Content-Type']);
        $corsRule->setExposeHeaders(['ETag']);
        $corsRule->setMaxAgeSeconds(3600);
        $corsRule->setCustomId('test-rule');

        $result = $this->corsService->convertRulesToXmlArray([$corsRule]);

        $expected = [
            'CORSConfiguration' => [
                '#CORSRule' => [
                    [
                        '#AllowedMethod' => ['GET', 'POST'],
                        '#AllowedOrigin' => ['*'],
                        '#AllowedHeader' => ['Content-Type'],
                        '#ExposeHeader' => ['ETag'],
                        'MaxAgeSeconds' => 3600,
                        'ID' => 'test-rule',
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testConvertRulesToXmlArrayMinimalRule(): void
    {
        $corsRule = new CorsRule(null, ['GET'], ['*']);

        $result = $this->corsService->convertRulesToXmlArray([$corsRule]);

        $expected = [
            'CORSConfiguration' => [
                '#CORSRule' => [
                    [
                        '#AllowedMethod' => ['GET'],
                        '#AllowedOrigin' => ['*'],
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testConvertRulesToXmlArrayMultipleRules(): void
    {
        $rule1 = new CorsRule(null, ['GET'], ['*']);
        $rule1->setCustomId('rule1');

        $rule2 = new CorsRule(null, ['POST', 'PUT'], ['https://example.com']);
        $rule2->setMaxAgeSeconds(7200);

        $result = $this->corsService->convertRulesToXmlArray([$rule1, $rule2]);

        $expected = [
            'CORSConfiguration' => [
                '#CORSRule' => [
                    [
                        '#AllowedMethod' => ['GET'],
                        '#AllowedOrigin' => ['*'],
                        'ID' => 'rule1',
                    ],
                    [
                        '#AllowedMethod' => ['POST', 'PUT'],
                        '#AllowedOrigin' => ['https://example.com'],
                        'MaxAgeSeconds' => 7200,
                    ]
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testConvertRulesToXmlArrayEmptyRules(): void
    {
        $result = $this->corsService->convertRulesToXmlArray([]);

        $expected = [
            'CORSConfiguration' => [
                '#CORSRule' => []
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider validHttpMethodsProvider
     */
    public function testAllValidHttpMethods(string $method): void
    {
        $xmlString = "<CORSRule>
            <AllowedMethod>$method</AllowedMethod>
            <AllowedOrigin>*</AllowedOrigin>
        </CORSRule>";

        $xmlElement = simplexml_load_string($xmlString);
        $rule = $this->corsService->parseCorsRule($xmlElement);

        $this->assertEquals([strtoupper($method)], $rule->getAllowedMethods());
    }

    public static function validHttpMethodsProvider(): array
    {
        return [
            ['GET'],
            ['PUT'],
            ['HEAD'],
            ['POST'],
            ['DELETE'],
            ['get'],
            ['put'],
            ['head'],
            ['post'],
            ['delete'],
        ];
    }
}
