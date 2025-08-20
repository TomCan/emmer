<?php

namespace App\Tests\Service\PolicyResolver;

use App\Service\PolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsCallPermittedTest extends TestCase
{
    private PolicyResolver $policyResolver;

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    /**
     * @param mixed[] $statements
     */
    #[DataProvider('statementsProvider')]
    public function testEvaluateStatement(array $statements, bool $expected): void
    {
        $result = $this->policyResolver->isCallPermitted($statements, ['emr:user:tom'], 's3:GetObject', 'emr:bucket:my-bucket/somefile.txt');

        $this->assertEquals($expected, $result);
    }

    // Data Provider
    /**
     * @return mixed[]
     */
    public static function statementsProvider(): array
    {
        $baseStatement = ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => ['emr:user:tom'], 'Resource' => ['emr:bucket:my-bucket/somefile.txt']];

        return [
            // single matching statement
            [
                [$baseStatement],
                true,
            ],
            // second statement matches
            [
                [
                    array_merge($baseStatement, ['Action' => ['s3:PutObject']]),
                    $baseStatement,
                ],
                true,
            ],
            // no statement matches
            [
                [
                    array_merge($baseStatement, ['Action' => ['s3:PutObject']]),
                ],
                false,
            ],
            // matching Allow, but also matching Deny
            [
                [
                    $baseStatement,
                    array_merge($baseStatement, ['Effect' => 'Deny']),
                ],
                false,
            ],
        ];
    }
}
