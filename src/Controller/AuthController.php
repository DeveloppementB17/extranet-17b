<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
        MailerInterface $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $csrf = (string) $request->request->get('_csrf_token', '');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('login_code_request', $csrf))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('auth_code');
            }

            // Anti-enumération: on répond toujours pareil.
            $this->addFlash('success', "Si un compte existe, un code vient d'être envoyé.");

            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user !== null) {
                $now = new \DateTimeImmutable();

                // Throttle simple (60s) pour éviter le spam.
                $last = $user->getLoginCodeRequestedAt();
                if ($last !== null && $last > $now->sub(new \DateInterval('PT60S'))) {
                    return $this->redirectToRoute('auth_code_verify_form', ['email' => $email]);
                }

                $code = (string) random_int(100000, 999999);
                $user->setLoginCodeHash(password_hash($code, PASSWORD_DEFAULT));
                $user->setLoginCodeExpiresAt($now->add(new \DateInterval('PT10M')));
                $user->setLoginCodeRequestedAt($now);
                $entityManager->flush();

                $message = (new Email())
                    ->from('no-reply@17b.test')
                    ->to($user->getEmail())
                    ->subject('Votre code de connexion 17b')
                    ->text("Votre code de connexion : {$code}\n\nIl expire dans 10 minutes.");

                $mailer->send($message);
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

