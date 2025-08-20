<?php

namespace App\Tests\Service\PolicyResolver;

use App\Entity\AccessKey;
use App\Entity\Bucket;
use App\Entity\Policy;
use App\Entity\User;
use App\Service\PolicyResolver;
use PHPUnit\Framework\TestCase;

class ConvertPoliciesTest extends TestCase
{
    private PolicyResolver $policyResolver;

    private string $validStatement = '{"Effect":"Allow","Action":["s3:GetObject"]}';
    /** @var mixed[] */
    private array $validStatementResult = ['Effect' => 'Allow', 'Action' => ['s3:GetObject'], 'Principal' => [], 'Resource' => []];

    protected function setUp(): void
    {
        $this->policyResolver = new PolicyResolver();
    }

    public function testStringPolicy(): void
    {
        $policy = '{"Statement": '.$this->validStatement.'}';
        $result = $this->policyResolver->convertPolicies($policy);
        $this->assertEquals([$this->validStatementResult], $result);
    }

    public function testMultipleStringPolicies(): void
    {
        $policy = '{"Statement": '.$this->validStatement.'}';
        $result = $this->policyResolver->convertPolicies($policy, $policy);
        $this->assertEquals([$this->validStatementResult, $this->validStatementResult], $result);
    }

    public function testBucketPolicy(): void
    {
        $bucket = new Bucket();
        $bucket->setName('my-bucket');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Resource":"emr:bucket:'.$bucket->getName().'"}}');
        $bucket->addPolicy($policy);

        $expected = array_merge($this->validStatementResult, ['Resource' => ['emr:bucket:'.$bucket->getName()]]);

        $result = $this->policyResolver->convertPolicies($bucket);
        $this->assertEquals([$expected], $result);
    }

    public function testOtherBucketPolicy(): void
    {
        $bucket = new Bucket();
        $bucket->setName('my-bucket');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Resource":"emr:bucket:not-'.$bucket->getName().'"}}');
        $bucket->addPolicy($policy);

        $result = $this->policyResolver->convertPolicies($bucket);
        $this->assertEquals([], $result);
    }

    public function testUserPolicy(): void
    {
        $user = new User();
        $user->setEmail('emmer@example.com');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Principal":"emr:user:'.$user->getEmail().'"}}');
        $user->addPolicy($policy);

        $expected = array_merge($this->validStatementResult, ['Principal' => ['emr:user:'.$user->getEmail()]]);

        $result = $this->policyResolver->convertPolicies($user);
        $this->assertEquals([$expected], $result);
    }

    public function testOtherUserPolicy(): void
    {
        $user = new User();
        $user->setEmail('emmer@example.com');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Principal":"emr:user:not-'.$user->getEmail().'"}}');
        $user->addPolicy($policy);

        $expected = array_merge($this->validStatementResult, ['Principal' => ['emr:user:'.$user->getEmail()]]);

        $result = $this->policyResolver->convertPolicies($user);
        $this->assertEquals([$expected], $result);
    }

    public function testUserWithNoPolicy(): void
    {
        $user = new User();
        $user->setEmail('emmer@example.com');

        $result = $this->policyResolver->convertPolicies($user);
        $this->assertEquals([], $result);
    }

    public function testUnsupportedObject(): void
    {
        $accessKey = new AccessKey();
        $result = $this->policyResolver->convertPolicies($accessKey);
        $this->assertEquals([], $result);
    }

    public function testMixedBagOfObjects(): void
    {
        $expected = [];

        $user = new User();
        $user->setEmail('emmer@example.com');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Principal":"emr:user:'.$user->getEmail().'","Sid":"User"}}');
        $user->addPolicy($policy);
        $expected[] = array_merge($this->validStatementResult, ['Principal' => ['emr:user:'.$user->getEmail()], 'Sid' => 'User']);

        $bucket = new Bucket();
        $bucket->setName('my-bucket');
        $policy = new Policy();
        $policy->setPolicy('{"Statement": '.substr($this->validStatement, 0, -1).',"Resource":"emr:bucket:'.$bucket->getName().'","Sid":"Bucket"}}');
        $bucket->addPolicy($policy);
        $expected[] = array_merge($this->validStatementResult, ['Resource' => ['emr:bucket:'.$bucket->getName()], 'Sid' => 'Bucket']);

        $stringPolicy = '{"Statement": '.substr($this->validStatement, 0, -1).',"Sid":"String"}}';
        $expected[] = array_merge($this->validStatementResult, ['Sid' => 'String']);

        $accessKey = new AccessKey();

        $result = $this->policyResolver->convertPolicies($user, $bucket, $stringPolicy, $accessKey);
        $this->assertEquals($expected, $result);
    }
}
