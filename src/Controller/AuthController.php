<?php

namespace App\Controller;

use App\Mailer\SystemMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($request->isMethod('POST')) {
            throw new \LogicException('This code should never be reached.');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This code should never be reached.');
    }

    #[Route('/login-code', name: 'auth_code', methods: ['GET', 'POST'])]
    public function requestCode(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SystemMailer $systemMailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $csrf = (string) $request->request->get('_csrf_token', '');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('login_code_request', $csrf))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('auth_code');
            }

            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user === null) {
                $this->addFlash('error', 'Aucun compte ne correspond à cet email.');

                return $this->redirectToRoute('auth_code');
            }

            $now = new \DateTimeImmutable();

            // Throttle simple (60s) pour éviter le spam.
            $last = $user->getLoginCodeRequestedAt();
            if ($last !== null && $last > $now->sub(new \DateInterval('PT60S'))) {
                $this->addFlash('success', 'Un code a déjà été envoyé récemment. Vérifie ta boîte email.');

                return $this->redirectToRoute('auth_code_verify_form', ['email' => $email]);
            }

            $code = (string) random_int(100000, 999999);
            $user->setLoginCodeHash(password_hash($code, PASSWORD_DEFAULT));
            $user->setLoginCodeExpiresAt($now->add(new \DateInterval('PT10M')));
            $user->setLoginCodeRequestedAt($now);
            $entityManager->flush();

            try {
                $systemMailer->sendText(
                    $user->getEmail(),
                    'Votre code de connexion 17b',
                    "Votre code de connexion : {$code}\n\nIl expire dans 10 minutes.",
                );
                $this->addFlash('success', 'Code envoyé. Vérifie ta boîte email.');
            } catch (\Throwable) {
                if ($this->getParameter('kernel.debug')) {
                    $this->addFlash('error', 'L’envoi du code par email a échoué. Réessaie dans un instant ou contacte l’administrateur.');
                }
            }

            return $this->redirectToRoute('auth_code_verify_form', ['email' => $email]);
        }

        return $this->render('auth/request_code.html.twig', [
            'csrf_token' => $csrfTokenManager->getToken('login_code_request')->getValue(),
        ]);
    }

    #[Route('/login-code/verify', name: 'auth_code_verify_form', methods: ['GET'])]
    public function verifyCodeForm(Request $request, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        return $this->render('auth/verify_code.html.twig', [
            'email' => (string) $request->query->get('email', ''),
            'csrf_token' => $csrfTokenManager->getToken('login_code_verify')->getValue(),
        ]);
    }

    #[Route('/login-code/verify', name: 'auth_code_verify', methods: ['POST'])]
    public function verifyCodeSubmit(): void
    {
        throw new \LogicException('This code should never be reached.');
    }
}

