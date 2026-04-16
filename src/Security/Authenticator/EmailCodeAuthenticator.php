<?php

namespace App\Security\Authenticator;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class EmailCodeAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'auth_code_verify'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = (string) $request->request->get('email', '');
        $code = (string) $request->request->get('code', '');
        $csrf = (string) $request->request->get('_csrf_token', '');

        $email = mb_strtolower(trim($email));
        $code = trim($code);

        if ($email === '' || $code === '') {
            throw new CustomUserMessageAuthenticationException('Email et code requis.');
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('login_code_verify', $csrf))) {
            throw new CustomUserMessageAuthenticationException('Jeton CSRF invalide.');
        }

        if (!preg_match('/^\d{6}$/', $code)) {
            throw new CustomUserMessageAuthenticationException('Code invalide.');
        }

        return new SelfValidatingPassport(
            new UserBadge($email, function (string $userIdentifier) use ($code): User {
                $user = $this->userRepository->findOneBy(['email' => $userIdentifier]);
                if (!$user instanceof User) {
                    throw new CustomUserMessageAuthenticationException('Email ou code incorrect.');
                }

                $hash = $user->getLoginCodeHash();
                $expiresAt = $user->getLoginCodeExpiresAt();

                if ($hash === null || $expiresAt === null) {
                    throw new CustomUserMessageAuthenticationException('Email ou code incorrect.');
                }

                if ($expiresAt <= new \DateTimeImmutable()) {
                    throw new CustomUserMessageAuthenticationException('Code expiré. Demande un nouveau code.');
                }

                if (!password_verify($code, $hash)) {
                    throw new CustomUserMessageAuthenticationException('Email ou code incorrect.');
                }

                return $user;
            }),
            []
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        $user = $token->getUser();
        if ($user instanceof User) {
            $user->clearLoginCode();
            $this->entityManager->flush();
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?RedirectResponse
    {
        $request->getSession()->getFlashBag()->add('error', $exception->getMessage());

        return new RedirectResponse($this->urlGenerator->generate('auth_code'));
    }
}

