<?php

namespace App\Tests\Service\PolicyResolver;

use App\Service\PolicyResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IsValueMatchingTest extends TestCase
{
    private PolicyResolver $policyResolver;

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    #[DataProvider('valueMatchingProvider')]
    public function testIsValueMatching(string $pattern, string $against, bool $expected): void
    {
        $reflection = new \ReflectionClass($this->policyResolver);
        $method = $reflection->getMethod('isValueMatching');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->policyResolver, [$pattern, $against]);

        $this->assertEquals($expected, $result);
    }

    // Data Provider
    /**
     * @return mixed[]
     */
    public static function valueMatchingProvider(): array
    {
        return [
            ['emr:user:tom', 'emr:user:tom', true],
            ['emr:user:bob', 'emr:user:tom', false],
            ['emr:user:*', 'emr:user:tom', true],
            ['emr:*:tom', 'emr:user:tom', true],
            ['emr:user:?om', 'emr:user:tom', true],
            ['emr:user:?om', 'emr:user:om', false],
            ['emr:user:#om', 'emr:user:#om', true],
            ['emr:user:tom{3}', 'emr:user:tommm', false],
            ['emr:user:tom{3}', 'emr:user:tom{3}', true],
            ['emr:user:$', 'emr:user:', false],
            ['emr:user:$', 'emr:user:$', true],
        ];
    }
}
