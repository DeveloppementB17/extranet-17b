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

        $managedClients = $entrepriseRepository->findSwitchableClientsForStaff($user);
        $preferredClients = [];
        $otherClients = [];

        if ($user->is17bAdmin()) {
            $preferredIds = $user->getManagedEntrepriseIds();
            $preferredIdMap = array_fill_keys($preferredIds, true);
            foreach ($managedClients as $client) {
                $id = $client->getId();
                if ($id !== null && isset($preferredIdMap[$id])) {
                    $preferredClients[] = $client;
                } else {
                    $otherClients[] = $client;
                }
            }
        }

        return $this->render('header/_staff_client_switcher.html.twig', [
            'managed_clients' => $managedClients,
            'preferred_clients' => $preferredClients,
            'other_clients' => $otherClients,
            'is_admin' => $user->is17bAdmin(),
            'selected_client' => $managedClientContext->getSelectedManagedEntreprise($user),
            'mine_scope' => $managedClientContext->isMineScope($user),
            'return_to' => (string) $request->query->get('return_to', '/'),
        ]);
    }

    #[Route('/staff/client/activate', name: 'staff_client_activate_switcher', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function activateClient(
        Request $request,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('activate_client_switcher', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $rawClientId = trim((string) $request->request->get('client_id', ''));
        if ($rawClientId === '') {
            $managedClientContext->clearSelectedManagedEntreprise($user);
            $this->addFlash('success', 'Vue : tous les clients.');

            return $this->redirectAfterActivate($request);
        }

        if ($rawClientId === ManagedClientContext::SWITCHER_VALUE_MINE) {
            if (!$user->is17bAdmin()) {
                $this->addFlash('error', 'Filtre non autorisé.');

                return $this->redirectToRoute('app_home');
            }

            if ($user->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucun client rattaché à votre compte. Configurez-les dans Mon compte.');

                return $this->redirectToRoute('app_account');
            }

            $managedClientContext->setMineScope($user);
            $this->addFlash('success', 'Vue : mes clients.');

            return $this->redirectAfterActivate($request);
        }

        $clientId = (int) $rawClientId;
        $entreprise = $entrepriseRepository->find($clientId);

        if (!$entreprise instanceof Entreprise || !$user->managesEntreprise($entreprise) || $entreprise->isAgency()) {
            $this->addFlash('error', 'Entreprise non autorisée.');

            return $this->redirectToRoute('app_home');
        }

        $managedClientContext->setSelectedManagedEntreprise($user, $entreprise);
        $this->addFlash('success', sprintf('Client actif : %s', $entreprise->getName()));

        return $this->redirectAfterActivate($request);
    }

    private function redirectAfterActivate(Request $request): Response
    {
        $returnTo = (string) $request->request->get('return_to', '');
        if ($returnTo !== '' && str_starts_with($returnTo, '/')) {
            return $this->redirect($returnTo);
        }

        return $this->redirectToRoute('app_home');
    }
}
