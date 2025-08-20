<?php

namespace App\Tests\Service\PolicyResolver;

use App\Service\PolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidateStatementTest extends TestCase
{
    private PolicyResolver $policyResolver;

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    #[DataProvider('statementsProvider')]
    public function testValidateStatement(array $statement, ?array $expected): void
    {
        $reflection = new \ReflectionClass($this->policyResolver);
        $method = $reflection->getMethod('validateStatement');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->policyResolver, [$statement]);

        $this->assertEquals($expected, $result);
    }

    // Data Provider
    public static function statementsProvider(): array
    {
        return [
            // minimal valid statement
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject']], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => []]],
            // additional key
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Tom' => 's3://tom'], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => []]],
            // invalid effect
            [['Effect' => 'Invalid', 'Action' => ['s3:GetObject']], null],
            // empty actions
            [['Effect' => 'Allow', 'Action' => []], null],
            // plain text principals
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => 'emr:user:tom'], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom'], 'Resource' => []]],
            // plain array principals
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom', 'emr:user:bob']], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom', 'emr:user:bob'], 'Resource' => []]],
            // array/key principals plain text
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr' => ['user:tom', 'user:bob']]], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom', 'emr:user:bob'], 'Resource' => []]],
            // array/key principals plain array
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr' => 'user:tom']], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom'], 'Resource' => []]],
            // invalid principal type
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr' => new \stdClass()]], null],
            // plain text resource
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Resource' => 'emr:bucket:my-bucket'], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => ['emr:bucket:my-bucket']]],
            // plain array resource
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Resource' => ['emr:bucket:my-bucket', 'emr:bucket:your-bucket']], ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => ['emr:bucket:my-bucket', 'emr:bucket:your-bucket']]],
            // invalid resource type
            [['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Resource' => ['emr' => ['bucket:my-bucket', 'bucket:your-bucket']]], null],
        ];
    }
}
