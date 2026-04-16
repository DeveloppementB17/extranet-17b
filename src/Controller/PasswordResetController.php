<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'auth_forgot_password', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim((string) $request->request->get('email', '')));
            $csrf = (string) $request->request->get('_csrf_token', '');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('password_reset_request', $csrf))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('auth_forgot_password');
            }

            // Anti-enumération: réponse identique.
            $this->addFlash('success', "Si un compte existe, un lien de réinitialisation vient d'être envoyé.");

            $user = $userRepository->findOneBy(['email' => $email]);
            if ($user !== null) {
                $now = new \DateTimeImmutable();
                $last = $user->getPasswordResetRequestedAt();

                // Throttle simple (60s) pour limiter le spam.
                if ($last !== null && $last > $now->sub(new \DateInterval('PT60S'))) {
                    return $this->redirectToRoute('auth_forgot_password');
                }

                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user->setPasswordResetTokenHash(password_hash($token, PASSWORD_DEFAULT));
                $user->setPasswordResetExpiresAt($now->add(new \DateInterval('PT30M')));
                $user->setPasswordResetRequestedAt($now);
                $entityManager->flush();

                $resetUrl = $this->generateUrl('auth_reset_password', ['token' => $token], 0);
                $absoluteResetUrl = $request->getSchemeAndHttpHost().$resetUrl;

                $message = (new Email())
                    ->from('no-reply@17b.test')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->text("Pour réinitialiser votre mot de passe, utilisez ce lien :\n\n{$absoluteResetUrl}\n\nCe lien expire dans 30 minutes.");

                $mailer->send($message);
            }

            return $this->redirectToRoute('auth_forgot_password');
        }

        return $this->render('auth/forgot_password.html.twig', [
            'csrf_token' => $csrfTokenManager->getToken('password_reset_request')->getValue(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'auth_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        CsrfTokenManagerInterface $csrfTokenManager,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        if ($request->isMethod('POST')) {
            $csrf = (string) $request->request->get('_csrf_token', '');
            $newPassword = (string) $request->request->get('password', '');

            if (!$csrfTokenManager->isTokenValid(new CsrfToken('password_reset', $csrf))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('auth_reset_password', ['token' => $token]);
            }

            if (mb_strlen($newPassword) < 10) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 10 caractères.');

                return $this->redirectToRoute('auth_reset_password', ['token' => $token]);
            }

            $user = $this->findUserByValidResetToken($userRepository, $token);
            if ($user === null) {
                $this->addFlash('error', 'Lien invalide ou expiré.');

                return $this->redirectToRoute('auth_forgot_password');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            $user->clearPasswordReset();
            $entityManager->flush();

            $this->addFlash('success', 'Mot de passe mis à jour. Tu peux te connecter.');

            return $this->redirectToRoute('app_login');
        }

        // GET: on vérifie le token pour afficher le formulaire.
        $user = $this->findUserByValidResetToken($userRepository, $token);
        if ($user === null) {
            $this->addFlash('error', 'Lien invalide ou expiré.');

            return $this->redirectToRoute('auth_forgot_password');
        }

        return $this->render('auth/reset_password.html.twig', [
            'token' => $token,
            'csrf_token' => $csrfTokenManager->getToken('password_reset')->getValue(),
        ]);
    }

    private function findUserByValidResetToken(UserRepository $userRepository, string $token): ?\App\Entity\User
    {
        // Recherche "brute" (peu d'utilisateurs) : on vérifie le hash côté PHP.
        // Si on a besoin d'optimiser plus tard, on ajoutera un token ID + hash en DB.
        $now = new \DateTimeImmutable();

        foreach ($userRepository->findAll() as $user) {
            $hash = $user->getPasswordResetTokenHash();
            $expiresAt = $user->getPasswordResetExpiresAt();

            if ($hash === null || $expiresAt === null) {
                continue;
            }

            if ($expiresAt <= $now) {
                continue;
            }

            if (password_verify($token, $hash)) {
                return $user;
            }
        }

        return null;
    }
}

