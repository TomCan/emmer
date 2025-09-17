<?php

namespace App\Tests\Service;

use App\Exception\Lifecycle\InvalidLifecycleRuleException;
use App\Service\BucketService;
use App\Service\LifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LifecycleServiceTest extends TestCase
{
    private LifecycleService $lifecycleService;
    private BucketService|MockObject $bucketService;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->bucketService = $this->createMock(BucketService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->lifecycleService = new LifecycleService($this->bucketService, $this->entityManager);
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
        $this->assertEquals('rule1', $result[0]['id']);
        $this->assertEquals(30, $result[0]['expiration_days']);
        $this->assertNull($result[0]['abortmpu']);

        // Second rule
        $this->assertEquals('rule2', $result[1]['id']);
        $this->assertEquals(7, $result[1]['abortmpu']);
        $this->assertNull($result[1]['expiration_days']);
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
        $this->assertEquals('single-rule', $result[0]['id']);
        $this->assertEquals(365, $result[0]['noncurrent_days']);
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

        $this->assertEquals('comprehensive-rule', $result['id']);
        $this->assertEquals(5, $result['abortmpu']);
        $this->assertEquals(90, $result['expiration_days']);
        $this->assertTrue($result['expiration_delete_marker']);
        $this->assertEquals(180, $result['noncurrent_days']);
        $this->assertEquals(5, $result['noncurrent_newer_versions']);
        $this->assertEquals('documents/', $result['filter']['prefix']);
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

        $this->assertNull($result['id']);
        $this->assertNull($result['abortmpu']);
        $this->assertNull($result['expiration_days']);
        $this->assertNull($result['expiration_date']);
        $this->assertNull($result['expiration_delete_marker']);
        $this->assertNull($result['noncurrent_days']);
        $this->assertNull($result['noncurrent_newer_versions']);
        $this->assertNull($result['filter']);
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

        $this->assertInstanceOf(\DateTime::class, $result['expiration_date']);
        $this->assertEquals('2024-12-31', $result['expiration_date']->format('Y-m-d'));
        $this->assertNull($result['expiration_days']);
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
        $result = $this->lifecycleService->parseLifecycleFilter($filter);

        $this->assertEquals('logs/', $result['prefix']);
        $this->assertNull($result['size_greater']);
        $this->assertNull($result['size_less']);
        $this->assertNull($result['tag']);
        $this->assertNull($result['and']);
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

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');

        $this->lifecycleService->parseLifecycleFilter($filter);
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
        $result = $this->lifecycleService->parseLifecycleFilter($filter);

        $this->assertNull($result['prefix']);
        $this->assertEquals(['key' => 'Environment', 'value' => 'Production'], $result['tag']);
        $this->assertNull($result['and']);
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
        $result = $this->lifecycleService->parseLifecycleFilter($filter);

        $this->assertNull($result['prefix']);
        $this->assertNull($result['tag']);
        $this->assertIsArray($result['and']);

        $andFilter = $result['and'];
        $this->assertEquals('documents/', $andFilter['prefix']);
        $this->assertEquals(1000, $andFilter['size_greater']);
        $this->assertIsArray($andFilter['tags']);
        $this->assertCount(2, $andFilter['tags']);
        $this->assertEquals(['key' => 'Department', 'value' => 'Finance'], $andFilter['tags'][0]);
        $this->assertEquals(['key' => 'Project', 'value' => 'Archive'], $andFilter['tags'][1]);
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
        $result = $this->lifecycleService->parseLifecycleFilter($filter, true);

        $this->assertEquals('temp/', $result['prefix']);
        $this->assertNull($result['size_greater']);
        $this->assertEquals(5000, $result['size_less']);
        $this->assertIsArray($result['tags']);
        $this->assertCount(1, $result['tags']);
        $this->assertEquals(['key' => 'Status', 'value' => 'Temporary'], $result['tags'][0]);
        // In And context, 'tag' and 'and' should not be set
        $this->assertArrayNotHasKey('tag', $result);
        $this->assertArrayNotHasKey('and', $result);
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
        $result = $this->lifecycleService->parseLifecycleFilter($filter);

        $this->assertNull($result['prefix']);
        $this->assertNull($result['size_greater']);
        $this->assertNull($result['size_less']);
        $this->assertNull($result['tag']);
        $this->assertNull($result['and']);
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

        $this->expectException(InvalidLifecycleRuleException::class);
        $this->expectExceptionMessage('Only one of Prefix, Tag, ObjectSizeGreaterThan, ObjectSizeLessThan, And is supported');

        $this->lifecycleService->parseLifecycleFilter($filter);
    }
}
