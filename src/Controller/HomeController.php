<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Tenant\ManagedClientContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
    ): Response
    {
        $user = $this->getUser();
        $managedClients = [];
        $selectedClient = null;
        if ($user instanceof User && $user->is17bUser()) {
            $managedClients = $entrepriseRepository->findNonAgencyByIdsOrdered($user->getManagedEntrepriseIds());
            $selectedClient = $managedClientContext->getSelectedManagedEntreprise($user);
        }

        return $this->render('home/index.html.twig', [
            'managed_clients' => $managedClients,
            'selected_client' => $selectedClient,
        ]);
    }

    #[Route('/staff/client/{id}/select', name: 'staff_client_select', methods: ['POST'])]
    #[IsGranted('ROLE_17B_USER')]
    public function selectClient(
        Entreprise $entreprise,
        Request $request,
        ManagedClientContext $managedClientContext,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('select_client_'.$entreprise->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$user->managesEntreprise($entreprise) || $entreprise->isAgency()) {
            throw $this->createAccessDeniedException();
        }

        $managedClientContext->setSelectedManagedEntreprise($user, $entreprise);
        $this->addFlash('success', sprintf('Client actif : %s', $entreprise->getName()));

        return $this->redirectToRoute('app_home');
    }
}

