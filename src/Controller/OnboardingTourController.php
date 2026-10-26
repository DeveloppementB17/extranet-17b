<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class OnboardingTourController extends AbstractController
{
    #[Route('/onboarding-tour/complete', name: 'onboarding_tour_complete', methods: ['POST'])]
    public function complete(
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $csrf = (string) $request->request->get('_token', $request->headers->get('X-CSRF-TOKEN', ''));
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('onboarding_tour', $csrf))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        if (!$user->hasCompletedOnboardingTour()) {
            $user->setOnboardingTourCompletedAt(new \DateTimeImmutable());
            $entityManager->flush();
        }

        return new JsonResponse(['ok' => true]);
    }

    #[Route('/onboarding-tour/reset', name: 'onboarding_tour_reset', methods: ['POST'])]
    public function reset(
        Request $request,
        EntityManagerInterface $entityManager,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $csrf = (string) $request->request->get('_token', $request->headers->get('X-CSRF-TOKEN', ''));
        if (!$csrfTokenManager->isTokenValid(new CsrfToken('onboarding_tour', $csrf))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $user->setOnboardingTourCompletedAt(null);
        $entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }
}
