<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Tenant\ManagedClientContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HeaderStaffClientSwitcherController extends AbstractController
{
    #[Route('/_header/staff-client-switcher', name: 'header_staff_client_switcher', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function widget(
        Request $request,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->is17bStaff()) {
            return new Response('');
        }

        $managedClients = $entrepriseRepository->findNonAgencyByIdsOrdered($user->getManagedEntrepriseIds());

        return $this->render('header/_staff_client_switcher.html.twig', [
            'managed_clients' => $managedClients,
            'selected_client' => $managedClientContext->getSelectedManagedEntreprise($user),
            'return_to' => (string) $request->query->get('return_to', '/'),
        ]);
    }

    #[Route('/staff/client/select', name: 'staff_client_select_switcher', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function select(
        Request $request,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('select_client_switcher', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $rawClientId = trim((string) $request->request->get('client_id', ''));
        if ($rawClientId === '') {
            $managedClientContext->clearSelectedManagedEntreprise($user);
            $this->addFlash('success', 'Aucun client actif.');

            $returnTo = (string) $request->request->get('return_to', '');
            if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
                return $this->redirect($returnTo);
            }

            return $this->redirectToRoute('app_home');
        }

        $clientId = (int) $rawClientId;
        $entreprise = $entrepriseRepository->find($clientId);

        if (!$entreprise instanceof Entreprise || !$user->managesEntreprise($entreprise) || $entreprise->isAgency()) {
            $this->addFlash('error', 'Entreprise non autorisée.');

            return $this->redirectToRoute('app_home');
        }

        $managedClientContext->setSelectedManagedEntreprise($user, $entreprise);
        $this->addFlash('success', sprintf('Client actif : %s', $entreprise->getName()));

        $returnTo = (string) $request->request->get('return_to', '');
        if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
            return $this->redirect($returnTo);
        }

        return $this->redirectToRoute('app_home');
    }
}
