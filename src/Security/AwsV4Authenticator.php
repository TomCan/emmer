<?php

namespace App\Security;

use App\Repository\AccessKeyRepository;
use App\Service\AwsSignatureV4Validator;
use App\Service\EncryptionService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class AwsV4Authenticator extends AbstractAuthenticator
{
    public function __construct(
        private AwsSignatureV4Validator $awsSignatureV4Validator,
        private AccessKeyRepository $accessKeyRepository,
        private EncryptionService $encryptionService,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return
            (
                $request->headers->has('authorization')
                && str_starts_with($request->headers->get('authorization'), 'AWS4-HMAC-SHA256')
            ) || (
                $request->query->has('X-Amz-Signature') && $request->query->has('X-Amz-Credential')
            );
    }

    public function authenticate(Request $request): Passport
    {
        $authKey = $this->awsSignatureV4Validator->extractAccessKey($request);
        if (!$authKey) {
            throw new CustomUserMessageAuthenticationException('Invalid authorization header.');
        } else {
            // find API key in database
            $accessKey = $this->accessKeyRepository->findOneBy(['name' => $authKey]);
            if (!$accessKey) {
                throw new CustomUserMessageAuthenticationException('Access key not found.'.$authKey);
            }

            try {
                $this->awsSignatureV4Validator->validateRequest($request, '', 's3', $this->encryptionService->decryptString($accessKey->getSecret(), false));

                return new SelfValidatingPassport(new UserBadge($accessKey->getUser()->getEmail()));
            } catch (AuthenticationException $e) {
                throw new CustomUserMessageAuthenticationException($e->getMessage());
            } catch (\Exception $e) {
                throw new CustomUserMessageAuthenticationException('General authentication failure.');
            }
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new Response(strtr($exception->getMessageKey(), $exception->getMessageData()), Response::HTTP_UNAUTHORIZED);
    }
}
