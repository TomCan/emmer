<?php

namespace App\Tests\Service;

use App\Domain\Lifecycle\ParsedLifecycleRule;
use App\Exception\Lifecycle\InvalidLifecycleRuleException;
use App\Repository\FileRepository;
use App\Service\BucketService;
use App\Service\LifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private LifecycleService $lifecycleService;
    private BucketService|MockObject $bucketService;
    private FileRepository|MockObject $fileRepository;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->bucketService = $this->createMock(BucketService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->fileRepository = $this->createMock(FileRepository::class);
        $this->lifecycleService = new LifecycleService($this->bucketService, $this->fileRepository, $this->entityManager);
    }

    /**
     * Test parseLifecycleRules with valid configuration.
     */
    public function testParseLifecycleRulesWithValidConfiguration(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<LifecycleConfiguration>
    <Rule>
        <ID>rule1</ID>
        <Status>Enabled</Status>
        <Expiration>
            <Days>30</Days>
        </Expiration>
    </Rule>
    <Rule>
        <ID>rule2</ID>
        <Status>Disabled</Status>
        <AbortIncompleteMultipartUpload>
            <DaysAfterInitiation>7</DaysAfterInitiation>
        </AbortIncompleteMultipartUpload>
    </Rule>
</LifecycleConfiguration>
XML;

        $result = $this->lifecycleService->parseLifecycleRules($xml);

        $this->assertCount(2, $result);

        // First rule
        $firstRule = $result[0];
        $this->assertEquals('rule1', $firstRule->getId());
        $this->assertEquals('Enabled', $firstRule->getStatus());
        $this->assertEquals(30, $firstRule->getExpirationDays());
        $this->assertNull($firstRule->getAbortIncompleteMultipartUploadDays());

        // Second rule
        $secondRule = $result[1];
        $this->assertEquals('rule2', $secondRule->getId());
        $this->assertEquals('Disabled', $secondRule->getStatus());
        $this->assertEquals(7, $secondRule->getAbortIncompleteMultipartUploadDays());
        $this->assertNull($secondRule->getExpirationDays());
    }

    /**
     * Test parseLifecycleRules with single rule.
     */
    public function testParseLifecycleRulesWithSingleRule(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<LifecycleConfiguration>
    <Rule>
        <ID>single-rule</ID>
        <Status>Enabled</Status>
        <NoncurrentVersionExpiration>
            <NoncurrentDays>365</NoncurrentDays>
        </NoncurrentVersionExpiration>
    </Rule>
</LifecycleConfiguration>
XML;

        $result = $this->lifecycleService->parseLifecycleRules($xml);

        $this->assertCount(1, $result);

        $rule = $result[0];
        $this->assertEquals('single-rule', $rule->getId());
        $this->assertEquals('Enabled', $rule->getStatus());
        $this->assertEquals(365, $rule->getNoncurrentVersionExpirationDays());
    }

    /**
     * Test parseLifecycleRules with invalid root element.
     */
    public function testParseLifecycleRulesWithInvalidRootElement(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InvalidRoot>
    <Rule>
        <Status>Enabled</Status>
    </Rule>
</InvalidRoot>
XML;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid lifecycle configuration');

        $this->lifecycleService->parseLifecycleRules($xml);
    }

    /**
     * Test parseLifecycleRule with all elements present.
     */
    public function testParseLifecycleRuleWithAllElements(): void
    {
        $xml = <<<XML
<Rule>
    <ID>comprehensive-rule</ID>
    <Status>Enabled</Status>
    <AbortIncompleteMultipartUpload>
        <DaysAfterInitiation>5</DaysAfterInitiation>
    </AbortIncompleteMultipartUpload>
    <Expiration>
        <Days>90</Days>
        <ExpiredObjectDeleteMarker>true</ExpiredObjectDeleteMarker>
    </Expiration>
    <NoncurrentVersionExpiration>
        <NoncurrentDays>180</NoncurrentDays>
        <NewerNoncurrentVersions>5</NewerNoncurrentVersions>
    </NoncurrentVersionExpiration>
    <Filter>
        <Prefix>documents/</Prefix>
    </Filter>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);
        $result = $this->lifecycleService->parseLifecycleRule($rule);

        $this->assertInstanceOf(ParsedLifecycleRule::class, $result);
        $this->assertEquals('comprehensive-rule', $result->getId());
        $this->assertEquals('Enabled', $result->getStatus());
        $this->assertEquals(5, $result->getAbortIncompleteMultipartUploadDays());
        $this->assertEquals(90, $result->getExpirationDays());
        $this->assertTrue($result->getExpiredObjectDeleteMarker());
        $this->assertEquals(180, $result->getNoncurrentVersionExpirationDays());
        $this->assertEquals(5, $result->getNoncurrentVersionNewerVersions());
        $this->assertEquals('documents/', $result->getFilterPrefix());
    }

    /**
     * Test parseLifecycleRule with minimal required elements.
     */
    public function testParseLifecycleRuleWithMinimalElements(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Disabled</Status>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);
        $result = $this->lifecycleService->parseLifecycleRule($rule);

        $this->assertInstanceOf(ParsedLifecycleRule::class, $result);
        $this->assertNull($result->getId());
        $this->assertEquals('Disabled', $result->getStatus());
        $this->assertNull($result->getAbortIncompleteMultipartUploadDays());
        $this->assertNull($result->getExpirationDays());
        $this->assertNull($result->getExpirationDate());
        $this->assertNull($result->getExpiredObjectDeleteMarker());
        $this->assertNull($result->getNoncurrentVersionExpirationDays());
        $this->assertNull($result->getNoncurrentVersionNewerVersions());
        $this->assertNull($result->getFilterPrefix());
    }

    /**
     * Test parseLifecycleRule with expiration date.
     */
    public function testParseLifecycleRuleWithExpirationDate(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Enabled</Status>
    <Expiration>
        <Date>2024-12-31T00:00:00Z</Date>
    </Expiration>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);
        $result = $this->lifecycleService->parseLifecycleRule($rule);

        $this->assertInstanceOf(ParsedLifecycleRule::class, $result);
        $this->assertInstanceOf(\DateTime::class, $result->getExpirationDate());
        $this->assertEquals('2024-12-31', $result->getExpirationDate()->format('Y-m-d'));
        $this->assertNull($result->getExpirationDays());
    }

    /**
     * Test parseLifecycleRule with invalid status.
     */
    public function testParseLifecycleRuleWithInvalidStatus(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Invalid</Status>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid Status in Rule element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with missing status.
     */
    public function testParseLifecycleRuleWithMissingStatus(): void
    {
        $xml = <<<XML
<Rule>
    <ID>test-rule</ID>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid Status in Rule element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with invalid ID length.
     */
    public function testParseLifecycleRuleWithInvalidIdLength(): void
    {
        $xml = <<<XML
<Rule>
    <ID></ID>
    <Status>Enabled</Status>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid ID in Rule element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with too long ID.
     */
    public function testParseLifecycleRuleWithTooLongId(): void
    {
        $longId = str_repeat('a', 256); // 256 characters, exceeds limit of 255
        $xml = <<<XML
<Rule>
    <ID>{$longId}</ID>
    <Status>Enabled</Status>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid ID in Rule element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with invalid AbortIncompleteMultipartUpload days.
     */
    public function testParseLifecycleRuleWithInvalidAbortMpuDays(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Enabled</Status>
    <AbortIncompleteMultipartUpload>
        <DaysAfterInitiation>0</DaysAfterInitiation>
    </AbortIncompleteMultipartUpload>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid DaysAfterInitiation in AbortIncompleteMultipartUpload element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with invalid expiration days.
     */
    public function testParseLifecycleRuleWithInvalidExpirationDays(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Enabled</Status>
    <Expiration>
        <Days>-1</Days>
    </Expiration>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid Days in Expiration element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with invalid noncurrent days.
     */
    public function testParseLifecycleRuleWithInvalidNoncurrentDays(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Enabled</Status>
    <NoncurrentVersionExpiration>
        <NoncurrentDays>0</NoncurrentDays>
    </NoncurrentVersionExpiration>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid NoncurrentDays in NoncurrentVersionExpiration element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleRule with invalid newer noncurrent versions.
     */
    public function testParseLifecycleRuleWithInvalidNewerNoncurrentVersions(): void
    {
        $xml = <<<XML
<Rule>
    <Status>Enabled</Status>
    <NoncurrentVersionExpiration>
        <NoncurrentDays>30</NoncurrentDays>
        <NewerNoncurrentVersions>100</NewerNoncurrentVersions>
    </NoncurrentVersionExpiration>
</Rule>
XML;

        $rule = new \SimpleXMLElement($xml);

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Invalid NewerNoncurrentVersions in NoncurrentVersionExpiration element');

        $this->lifecycleService->parseLifecycleRule($rule);
    }

    /**
     * Test parseLifecycleFilter with prefix only.
     */
    public function testParseLifecycleFilterWithPrefix(): void
    {
        $xml = <<<XML
<Filter>
    <Prefix>logs/</Prefix>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();
        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);

        $this->assertEquals('logs/', $parsedRule->getFilterPrefix());
        $this->assertNull($parsedRule->getFilterSizeGreaterThan());
        $this->assertNull($parsedRule->getFilterSizeLessThan());
        $this->assertNull($parsedRule->getFilterTag());
        $this->assertFalse($parsedRule->hasAnd());
    }

    /**
     * Test parseLifecycleFilter with size constraints.
     */
    public function testParseLifecycleFilterWithSizeConstraints(): void
    {
        $xml = <<<XML
<Filter>
    <ObjectSizeGreaterThan>1024</ObjectSizeGreaterThan>
    <ObjectSizeLessThan>10485760</ObjectSizeLessThan>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');

        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);
    }

    /**
     * Test parseLifecycleFilter with single tag.
     */
    public function testParseLifecycleFilterWithSingleTag(): void
    {
        $xml = <<<XML
<Filter>
    <Tag>
        <Key>Environment</Key>
        <Value>Production</Value>
    </Tag>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();
        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);

        $this->assertNull($parsedRule->getFilterPrefix());
        $this->assertEquals(['Key' => 'Environment', 'Value' => 'Production'], $parsedRule->getFilterTag());
        $this->assertFalse($parsedRule->hasAnd());
    }

    /**
     * Test parseLifecycleFilter with And block.
     */
    public function testParseLifecycleFilterWithAndBlock(): void
    {
        $xml = <<<XML
<Filter>
    <And>
        <Prefix>documents/</Prefix>
        <ObjectSizeGreaterThan>1000</ObjectSizeGreaterThan>
        <Tag>
            <Key>Department</Key>
            <Value>Finance</Value>
        </Tag>
        <Tag>
            <Key>Project</Key>
            <Value>Archive</Value>
        </Tag>
    </And>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();
        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);

        $this->assertNull($parsedRule->getFilterPrefix());
        $this->assertNull($parsedRule->getFilterTag());
        $this->assertTrue($parsedRule->hasAnd());

        $this->assertEquals('documents/', $parsedRule->getFilterAndPrefix());
        $this->assertEquals(1000, $parsedRule->getFilterAndSizeGreaterThan());
        $this->assertIsArray($parsedRule->getFilterAndTags());
        $this->assertCount(2, $parsedRule->getFilterAndTags());
        $this->assertEquals(['Key' => 'Department', 'Value' => 'Finance'], $parsedRule->getFilterAndTags()[0]);
        $this->assertEquals(['Key' => 'Project', 'Value' => 'Archive'], $parsedRule->getFilterAndTags()[1]);
    }

    /**
     * Test parseLifecycleFilter with And block (and=true parameter).
     */
    public function testParseLifecycleFilterInAndContext(): void
    {
        $xml = <<<XML
<And>
    <Prefix>temp/</Prefix>
    <ObjectSizeLessThan>5000</ObjectSizeLessThan>
    <Tag>
        <Key>Status</Key>
        <Value>Temporary</Value>
    </Tag>
</And>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();
        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter, true);

        $this->assertEquals('temp/', $parsedRule->getFilterAndPrefix());
        $this->assertNull($parsedRule->getFilterAndSizeGreaterThan());
        $this->assertEquals(5000, $parsedRule->getFilterAndSizeLessThan());
        $this->assertIsArray($parsedRule->getFilterAndTags());
        $this->assertCount(1, $parsedRule->getFilterAndTags());
        $this->assertEquals(['Key' => 'Status', 'Value' => 'Temporary'], $parsedRule->getFilterAndTags()[0]);

        // In And context, normal filter properties should not be set
        $this->assertNull($parsedRule->getFilterPrefix());
        $this->assertNull($parsedRule->getFilterTag());
    }

    /**
     * Test parseLifecycleFilter with empty filter.
     */
    public function testParseLifecycleFilterWithEmptyFilter(): void
    {
        $xml = <<<XML
<Filter>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();
        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);

        $this->assertNull($parsedRule->getFilterPrefix());
        $this->assertNull($parsedRule->getFilterSizeGreaterThan());
        $this->assertNull($parsedRule->getFilterSizeLessThan());
        $this->assertNull($parsedRule->getFilterTag());
        $this->assertFalse($parsedRule->hasAnd());
    }

    /**
     * Test parseLifecycleFilter with multiple conflicting elements.
     */
    public function testParseLifecycleFilterWithConflictingElements(): void
    {
        $xml = <<<XML
<Filter>
    <Prefix>docs/</Prefix>
    <Tag>
        <Key>Type</Key>
        <Value>Document</Value>
    </Tag>
</Filter>
XML;

        $filter = new \SimpleXMLElement($xml);
        $parsedRule = new ParsedLifecycleRule();

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');

        $this->lifecycleService->parseLifecycleFilter($parsedRule, $filter);
    }
}
