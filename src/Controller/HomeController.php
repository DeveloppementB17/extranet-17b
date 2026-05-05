<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\DocumentRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditRepository;
use App\Tenant\ManagedClientContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function __invoke(
        EntrepriseRepository $entrepriseRepository,
        DocumentRepository $documentRepository,
        TimeCreditRepository $timeCreditRepository,
        ManagedClientContext $managedClientContext,
    ): Response
    {
        $user = $this->getUser();
        if ($user instanceof User && $this->isGranted('ROLE_17B_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $managedClients = [];
        $selectedClient = null;
        $selectedClientCredits = [];
        $selectedClientDocumentFolders = [];
        if ($user instanceof User && $user->is17bStaff()) {
            $managedClients = $entrepriseRepository->findNonAgencyByIdsOrdered($user->getManagedEntrepriseIds());
            $selectedClient = $managedClientContext->getSelectedManagedEntreprise($user);

            if ($selectedClient instanceof Entreprise) {
                $selectedClientDocumentFolders = $documentRepository->findCategorySummariesByEntreprise($selectedClient);
                $selectedClientCredits = $timeCreditRepository->findAccessibleForUser($user, $selectedClient);
            }
        }
        if ($user instanceof User && $user->isCustomerActor()) {
            $selectedClient = $user->getEntreprise();
            if ($selectedClient instanceof Entreprise && !$selectedClient->isAgency()) {
                $selectedClientDocumentFolders = $documentRepository->findCategorySummariesByEntreprise($selectedClient);
                $selectedClientCredits = $timeCreditRepository->findAccessibleForUser($user, $selectedClient);
            }
        }

        return $this->render('home/index.html.twig', [
            'managed_clients' => $managedClients,
            'selected_client' => $selectedClient,
            'selected_client_document_folders' => $selectedClientDocumentFolders,
            'selected_client_credits' => $selectedClientCredits,
        ]);
    }

    #[Route('/staff/client/{id}/select', name: 'staff_client_select', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
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

