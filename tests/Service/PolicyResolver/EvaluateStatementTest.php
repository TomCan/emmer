<?php

namespace App\Tests\Service\PolicyResolver;

use App\Service\PolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EvaluateStatementTest extends TestCase
{
    private PolicyResolver $policyResolver;

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    /**
     * @param mixed[] $statement
     */
    #[DataProvider('statementProvider')]
    public function testEvaluateStatement(array $statement, string $principal, string $action, string $resource, int $expected): void
    {
        $reflection = new \ReflectionClass($this->policyResolver);
        $method = $reflection->getMethod('evaluateStatement');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->policyResolver, [$statement, $principal, $action, $resource]);

        $this->assertEquals($expected, $result);
    }

    // Data Provider
    /**
     * @return mixed[]
     */
    public static function statementProvider(): array
    {
        $baseStatement = ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => []];

        return [
            // exact match of principal and resource
            [
                array_merge($baseStatement, ['Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                1,
            ],
            // exact match of principal, wildcard match on resource
            [
                array_merge($baseStatement, ['Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/*']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                1,
            ],
            // exact match of principal, no match on resource
            [
                array_merge($baseStatement, ['Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/otherfile.txt',
                0,
            ],
            // wildcard match of principal (not possible -> no match), exact match on resource
            [
                array_merge($baseStatement, ['Principal' => ['emr:user:*'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                0,
            ],
            // * match of principal, exact match on resource
            [
                array_merge($baseStatement, ['Principal' => ['*'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                1,
            ],
            // exact match of principal and resource, but Deny effect
            [
                array_merge($baseStatement, ['Effect' => 'Deny', 'Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                -1,
            ],
            // exact match of principal and resource, matching action out of multiple actions
            [
                array_merge($baseStatement, ['Action' => ['s3:PutObject', 's3:GetObject'], 'Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:GetObject',
                'emr:bucket:my-bucket/somefile.txt',
                1,
            ],
            // exact match of principal and resource, no matching action
            [
                array_merge($baseStatement, ['Action' => ['s3:PutObject', 's3:GetObject'], 'Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:DeleteObject',
                'emr:bucket:my-bucket/somefile.txt',
                0,
            ],
            // exact match of principal and resource, wildcard on action
            [
                array_merge($baseStatement, ['Action' => ['s3:*'], 'Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']]),
                'emr:user:tom',
                's3:DeleteObject',
                'emr:bucket:my-bucket/somefile.txt',
                1,
            ],
        ];
    }
}
