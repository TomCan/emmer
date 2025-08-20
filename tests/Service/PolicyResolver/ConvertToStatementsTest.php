<?php

namespace App\Tests\Service\PolicyResolver;

use App\Service\PolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConvertToStatementsTest extends TestCase
{
    private PolicyResolver $policyResolver;

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    #[DataProvider('policyProvider')]
    public function testConvertToStatements(string $policy, ?array $expected): void
    {
        $reflection = new \ReflectionClass($this->policyResolver);
        $method = $reflection->getMethod('convertToStatements');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->policyResolver, [$policy]);

        $this->assertEquals($expected, $result);
    }

    // Data Provider
    public static function policyProvider(): array
    {
        $validStatement = '{"Effect":"Allow","Action":["s3:GetObject"]}';
        $validStatementResult = ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => []];

        return [
            // minimal valid single policy
            ['{"Statement": '.$validStatement.'}', [$validStatementResult]],
            // minimal valid single policy as array
            ['{"Statement": ['.$validStatement.']}', [$validStatementResult]],
            // minimal valid multiple policies
            ['{"Statement": ['.$validStatement.','.$validStatement.']}', [$validStatementResult, $validStatementResult]],
            // invalid policy (statement missing)
            ['{"NotAStatement": ['.$validStatement.','.$validStatement.']}', null],
            // valid policy with no valid statements
            ['{"Statement": [{"NotAStatement": true}]}', []],
            // valid policy with some valid statements but not all
            ['{"Statement": ['.$validStatement.',{"NotAStatement": true},'.$validStatement.']}', [$validStatementResult, $validStatementResult]],
        ];
    }
}
