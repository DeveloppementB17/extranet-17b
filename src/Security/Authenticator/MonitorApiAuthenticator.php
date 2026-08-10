<?php

namespace App\Security\Authenticator;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class MonitorApiAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/api/monitor');
    }

    public function authenticate(Request $request): Passport
    {
        $expected = $_ENV['MONITOR_API_TOKEN'] ?? getenv('MONITOR_API_TOKEN') ?: '';
        if ($expected === '') {
            throw new CustomUserMessageAuthenticationException('API monitor non configurée.');
        }

        $token = $this->extractToken($request);
        if ($token === null || !hash_equals($expected, $token)) {
            throw new CustomUserMessageAuthenticationException('Token API invalide.');
        }

        return new SelfValidatingPassport(new UserBadge('monitor-api', static fn (): MonitorApiUser => new MonitorApiUser()));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $matches) === 1) {
            return trim($matches[1]);
        }

        $custom = $request->headers->get('X-Monitor-Token');
        if (is_string($custom) && trim($custom) !== '') {
            return trim($custom);
        }

        return null;
    }
}
