<?php

namespace App\Tests\Service\AuthorizationService;

use App\Entity\User;
use App\Service\AuthorizationService;
use App\Service\PolicyResolver;
use App\Service\PrincipalService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class RequireTest extends TestCase
{
    private AuthorizationService $authorizationService;
    private User $user;

    public function setUp(): void
    {
        $this->authorizationService = new AuthorizationService(new PolicyResolver(), new PrincipalService());
        $this->user = new User();
        $this->user->setEmail('tom');
    }

    public function testAnyOne(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/somefile.txt"]}}';
        $policy2 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/otherfile.txt"]}}';

        $this->authorizationService->requireAny(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/otherfile.txt'],
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:other-bucket/somefile.txt'],
            ],
            $policy1, $policy2,
        );

        // we don't actually need to test anything here, as the above call should throw an exception if it fails.
        $this->assertTrue(true);
    }

    public function testAnyNone(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/somefile.txt"]}}';
        $policy2 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/otherfile.txt"]}}';

        $this->expectException(AccessDeniedException::class);
        $this->authorizationService->requireAny(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/yet-another-file.txt'],
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:other-bucket/somefile.txt'],
            ],
            $policy1, $policy2,
        );
    }

    public function testAnyDenied(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';
        $policy2 = '{"Statement": {"Effect": "Deny", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/secret-file.txt"]}}';

        $this->expectException(AccessDeniedException::class);
        $this->authorizationService->requireAny(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/secret-file.txt'],
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:other-bucket/somefile.txt'],
            ],
            $policy1, $policy2,
        );
    }

    public function testAnyRoot(): void
    {
        $this->user->setRoles(['ROOT']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';
        $policy2 = '{"Statement": {"Effect": "Deny", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/secret-file.txt"]}}';

        $this->authorizationService->requireAll(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/secret-file.txt'],
            ],
            $policy1, $policy2,
        );

        // we don't actually need to test anything here, as the above call should throw an exception if it fails.
        $this->assertTrue(true);
    }

    public function testAllBoth(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/somefile.txt"]}}';
        $policy2 = '{"Statement": {"Effect": "Allow", "Action": ["s3:PutObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';

        $this->authorizationService->requireAll(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/somefile.txt'],
                ['action' => 's3:PutObject', 'resource' => 'emr:bucket:my-bucket/somefile.txt'],
            ],
            $policy1, $policy2,
        );

        // we don't actually need to test anything here, as the above call should throw an exception if it fails.
        $this->assertTrue(true);
    }

    public function testAllOne(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/somefile.txt"]}}';
        $policy2 = '{"Statement": {"Effect": "Allow", "Action": ["s3:PutObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';

        $this->expectException(AccessDeniedException::class);
        $this->authorizationService->requireAll(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/somefile.txt'],
                ['action' => 's3:PutObject', 'resource' => 'emr:bucket:other-bucket/somefile.txt'],
            ],
            $policy1, $policy2,
        );
    }

    public function testAllDenied(): void
    {
        $this->user->setRoles(['USER']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';
        $policy2 = '{"Statement": {"Effect": "Deny", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/secret-file.txt"]}}';

        $this->expectException(AccessDeniedException::class);
        $this->authorizationService->requireAll(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/some-file.txt'],
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/secret-file.txt'],
            ],
            $policy1, $policy2,
        );
    }

    public function testAllRoot(): void
    {
        $this->user->setRoles(['ROOT']);

        $policy1 = '{"Statement": {"Effect": "Allow", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/*"]}}';
        $policy2 = '{"Statement": {"Effect": "Deny", "Action": ["s3:GetObject"], "Principal": ["emr:user:tom"], "Resource": ["emr:bucket:my-bucket/secret-file.txt"]}}';

        $this->authorizationService->requireAll(
            $this->user,
            [
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/some-file.txt'],
                ['action' => 's3:GetObject', 'resource' => 'emr:bucket:my-bucket/secret-file.txt'],
            ],
            $policy1, $policy2,
        );

        // we don't actually need to test anything here, as the above call should throw an exception if it fails.
        $this->assertTrue(true);
    }
}
