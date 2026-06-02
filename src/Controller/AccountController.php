<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountPasswordChangeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roles = $user->getRoles();
        usort($roles, static function (string $a, string $b): int {
            $la = preg_replace('/^ROLE_/', '', $a) ?: $a;
            $lb = preg_replace('/^ROLE_/', '', $b) ?: $b;

            return strcasecmp($la, $lb);
        });

        $passwordForm = $this->createForm(AccountPasswordChangeType::class);
        $passwordForm->handleRequest($request);

        if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
            $currentPassword = (string) $passwordForm->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $passwordForm->get('currentPassword')->addError(
                    new FormError('Le mot de passe actuel est incorrect.'),
                );
            } else {
                $newPassword = (string) $passwordForm->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->clearPasswordReset();
                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

                return $this->redirectToRoute('app_account');
            }
        }

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'display_roles' => $roles,
            'password_form' => $passwordForm,
        ]);
    }
}
