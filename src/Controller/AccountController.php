<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
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

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'display_roles' => $roles,
        ]);
    }
}
