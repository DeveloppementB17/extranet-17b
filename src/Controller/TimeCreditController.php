<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Entity\User;
use App\Form\TimeCreditInterventionType;
use App\Form\TimeCreditQuickInterventionType;
use App\Form\TimeCreditType;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditCategoryRepository;
use App\Repository\TimeCreditMovementRepository;
use App\Repository\TimeCreditRepository;
use App\Security\Voter\TimeCreditVoter;
use App\Tenant\ManagedClientContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/credits-temps')]
final class TimeCreditController extends AbstractController
{
    #[Route('', name: 'time_credit_index', methods: ['GET'])]
    public function index(
        Request $request,
        TimeCreditRepository $timeCreditRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $forcedEntreprise = null;
        if ($actor->is17bStaff()) {
            $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($actor);
            if ($actor->is17bUser() && !$forcedEntreprise instanceof Entreprise && $actor->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à ton compte.');

                return $this->redirectToRoute('app_home');
            }
        }

        $isAdminListView = $actor->is17bStaff() || $actor->isCustomerActor();
        $credits = $timeCreditRepository->findAccessibleForUser($actor, $forcedEntreprise);
        $availableEntreprises = [];
        $availableCategories = [];
        foreach ($credits as $credit) {
            $entreprise = $credit->getEntreprise();
            if ($entreprise !== null) {
                $availableEntreprises[$entreprise->getId() ?? 0] = $entreprise->getName();
            }
            $category = $credit->getCategory();
            if ($category !== null) {
                $availableCategories[$category->getId() ?? 0] = $category->getName();
            }
        }
        asort($availableEntreprises, SORT_NATURAL | SORT_FLAG_CASE);
        asort($availableCategories, SORT_NATURAL | SORT_FLAG_CASE);

        if ($isAdminListView) {
            $search = trim((string) $request->query->get('q', ''));
            $entrepriseFilter = (int) $request->query->get('entreprise', 0);
            $categoryFilter = (int) $request->query->get('category', 0);
            $statusFilter = (string) $request->query->get('status', 'all');
            $sort = (string) $request->query->get('sort', 'created_at');
            $direction = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $allowedSorts = ['created_at', 'title', 'entreprise', 'category', 'remaining'];
            if (!\in_array($sort, $allowedSorts, true)) {
                $sort = 'created_at';
            }

            $credits = array_values(array_filter($credits, static function (TimeCredit $credit) use (
                $search,
                $entrepriseFilter,
                $categoryFilter,
                $statusFilter,
            ): bool {
                if ($entrepriseFilter > 0 && $credit->getEntreprise()?->getId() !== $entrepriseFilter) {
                    return false;
                }
                if ($categoryFilter > 0 && $credit->getCategory()?->getId() !== $categoryFilter) {
                    return false;
                }
                if ($statusFilter === 'active' && $credit->isArchived()) {
                    return false;
                }
                if ($statusFilter === 'archived' && !$credit->isArchived()) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $credit->getTitle(),
                    $credit->getDossierNumber(),
                    $credit->getEntreprise()?->getName(),
                    $credit->getCategory()?->getName(),
                ])));

                return str_contains($haystack, mb_strtolower($search));
            }));

            usort($credits, static function (TimeCredit $left, TimeCredit $right) use ($sort, $direction): int {
                $result = match ($sort) {
                    'title' => strcasecmp($left->getTitle(), $right->getTitle()),
                    'entreprise' => strcasecmp((string) $left->getEntreprise()?->getName(), (string) $right->getEntreprise()?->getName()),
                    'category' => strcasecmp((string) $left->getCategory()?->getName(), (string) $right->getCategory()?->getName()),
                    'remaining' => $left->getRemainingMinutes() <=> $right->getRemainingMinutes(),
                    default => $left->getCreatedAt() <=> $right->getCreatedAt(),
                };

                return $direction === 'asc' ? $result : -$result;
            });
        }

        return $this->render('time_credit/index.html.twig', [
            'credits' => $credits,
            'is_admin_list_view' => $isAdminListView,
            'search_query' => $isAdminListView ? trim((string) $request->query->get('q', '')) : '',
            'filter_entreprise' => $isAdminListView ? (int) $request->query->get('entreprise', 0) : 0,
            'filter_category' => $isAdminListView ? (int) $request->query->get('category', 0) : 0,
            'filter_status' => $isAdminListView ? (string) $request->query->get('status', 'all') : 'all',
            'sort_field' => $isAdminListView ? (string) $request->query->get('sort', 'created_at') : 'created_at',
            'sort_direction' => $isAdminListView && strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            'available_entreprises' => $availableEntreprises,
            'available_categories' => $availableCategories,
        ]);
    }

    #[Route('/interventions/formulaire', name: 'time_credit_intervention_widget', methods: ['GET'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function interventionWidget(
        Request $request,
        ManagedClientContext $managedClientContext,
        TimeCreditRepository $timeCreditRepository,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->is17bStaff()) {
            throw $this->createAccessDeniedException();
        }

        $selectedEntreprise = $managedClientContext->getSelectedManagedEntreprise($actor);
        $choices = $selectedEntreprise instanceof Entreprise
            ? $timeCreditRepository->findActiveByEntreprise($selectedEntreprise)
            : [];
        $preselectedCredit = \count($choices) === 1 ? $choices[0] : null;

        $returnTo = (string) ($request->query->get('return_to') ?? '/credits-temps');
        $form = $this->createForm(TimeCreditQuickInterventionType::class, null, [
            'time_credit_choices' => $choices,
            'preselected_time_credit' => $preselectedCredit,
            'return_to' => $returnTo,
            'action' => $this->generateUrl('time_credit_intervention_quick_create'),
        ]);

        return $this->render('time_credit/_quick_intervention_form.html.twig', [
            'form' => $form,
            'selected_entreprise' => $selectedEntreprise,
            'has_available_credits' => $choices !== [],
        ]);
    }

    #[Route('/interventions/creer', name: 'time_credit_intervention_quick_create', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function quickCreateIntervention(
        Request $request,
        EntityManagerInterface $entityManager,
        ManagedClientContext $managedClientContext,
        TimeCreditRepository $timeCreditRepository,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->is17bStaff()) {
            throw $this->createAccessDeniedException();
        }

        $selectedEntreprise = $managedClientContext->getSelectedManagedEntreprise($actor);
        if (!$selectedEntreprise instanceof Entreprise) {
            $this->addFlash('error', 'Sélectionne d’abord une entreprise depuis le tableau de bord.');

            return $this->redirectToRoute('app_home');
        }

        $choices = $timeCreditRepository->findActiveByEntreprise($selectedEntreprise);
        $preselectedCredit = \count($choices) === 1 ? $choices[0] : null;
        $form = $this->createForm(TimeCreditQuickInterventionType::class, null, [
            'time_credit_choices' => $choices,
            'preselected_time_credit' => $preselectedCredit,
            'return_to' => (string) ($request->request->all('time_credit_quick_intervention')['returnTo'] ?? '/credits-temps'),
            'action' => $this->generateUrl('time_credit_intervention_quick_create'),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Intervention invalide : vérifie les champs saisis.');

            return $this->redirect($this->resolveReturnPath((string) $form->get('returnTo')->getData()));
        }

        $credit = $form->get('timeCredit')->getData();
        if (!$credit instanceof TimeCredit) {
            $this->addFlash('error', 'Crédit temps invalide.');

            return $this->redirect($this->resolveReturnPath((string) $form->get('returnTo')->getData()));
        }

        $this->denyAccessUnlessGranted(TimeCreditVoter::INTERVENE, $credit);

        /** @var \DateTimeImmutable $occurredAt */
        $occurredAt = $form->get('occurredAt')->getData();
        $duration = (int) $form->get('durationMinutes')->getData();
        $description = (string) $form->get('description')->getData();

        if ($duration <= 0) {
            $this->addFlash('error', 'La durée doit être supérieure à 0 minute.');

            return $this->redirect($this->resolveReturnPath((string) $form->get('returnTo')->getData()));
        }
        if ($duration > $credit->getRemainingMinutes()) {
            $this->addFlash('error', 'La durée dépasse le solde disponible du crédit.');

            return $this->redirect($this->resolveReturnPath((string) $form->get('returnTo')->getData()));
        }

        $movement = (new TimeCreditMovement())
            ->setTimeCredit($credit)
            ->setCreatedBy($actor)
            ->setType(TimeCreditMovement::TYPE_INTERVENTION)
            ->setDeltaMinutes(-$duration)
            ->setDescription($description)
            ->setOccurredAt($occurredAt);

        $credit->setRemainingMinutes($credit->getRemainingMinutes() - $duration);
        if ($credit->getRemainingMinutes() <= 0) {
            $credit->setArchived(true);
        }
        $credit->addMovement($movement);
        $entityManager->flush();
        $this->addFlash('success', 'Intervention enregistrée.');

        return $this->redirect($this->resolveReturnPath((string) $form->get('returnTo')->getData()));
    }

    #[Route('/nouveau', name: 'time_credit_new', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
        TimeCreditCategoryRepository $categoryRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->is17bStaff()) {
            throw $this->createAccessDeniedException();
        }

        $selectedEntreprise = $managedClientContext->getSelectedManagedEntreprise($actor);
        if ($actor->is17bUser() && !$selectedEntreprise instanceof Entreprise && $actor->getManagedEntrepriseIds() === []) {
            $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à ton compte.');

            return $this->redirectToRoute('app_home');
        }

        $allowedEntreprises = ($actor->is17bUser() && $selectedEntreprise instanceof Entreprise)
            ? [$selectedEntreprise]
            : $this->allowedClientEntreprises($actor, $entrepriseRepository);
        if ($allowedEntreprises === []) {
            $this->addFlash('error', 'Aucune entreprise cliente disponible.');

            return $this->redirectToRoute('time_credit_index');
        }

        $credit = new TimeCredit();
        $form = $this->createForm(TimeCreditType::class, $credit, [
            'entreprise_choices' => $allowedEntreprises,
            'category_choices' => $categoryRepository->findAllOrdered(),
            'allow_archive_field' => false,
            'preselected_entreprise' => $selectedEntreprise,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise = $credit->getEntreprise();
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency() || !$this->canActOnEntreprise($actor, $entreprise)) {
                throw $this->createAccessDeniedException();
            }

            $total = $credit->getTotalMinutes();
            if ($total <= 0) {
                $this->addFlash('error', 'Le total doit être strictement positif.');

                return $this->render('time_credit/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouveau crédit temps',
                ]);
            }

            $credit->setTitle('Crédit du '.(new \DateTimeImmutable())->format('d/m/Y H:i'));
            $credit->setRemainingMinutes($total);
            $credit->setArchived(false);
            $credit->setCreatedBy($actor);

            $movement = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($actor)
                ->setType(TimeCreditMovement::TYPE_ALLOCATION)
                ->setDeltaMinutes($total)
                ->setDescription('Création du crédit temps')
                ->setOccurredAt(new \DateTimeImmutable());
            $credit->addMovement($movement);

            $entityManager->persist($credit);
            $entityManager->flush();
            $this->addFlash('success', 'Crédit temps créé.');

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('time_credit/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau crédit temps',
        ]);
    }

    #[Route('/{id}', name: 'time_credit_show', methods: ['GET'])]
    public function show(TimeCredit $credit, TimeCreditMovementRepository $movementRepository): Response
    {
        $this->denyAccessUnlessGranted(TimeCreditVoter::VIEW, $credit);

        return $this->render('time_credit/show.html.twig', [
            'credit' => $credit,
            'movements' => $movementRepository->findByCreditOrdered($credit),
        ]);
    }

    #[Route('/{id}/modifier', name: 'time_credit_edit', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function edit(
        Request $request,
        TimeCredit $credit,
        EntityManagerInterface $entityManager,
        TimeCreditCategoryRepository $categoryRepository,
    ): Response {
        $this->denyAccessUnlessGranted(TimeCreditVoter::MANAGE, $credit);
        if ($this->isCreditStarted($credit)) {
            $this->addFlash('error', 'Ce crédit a déjà été entamé et ne peut plus être modifié.');

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        $originalTotal = $credit->getTotalMinutes();
        $originalRemaining = $credit->getRemainingMinutes();

        $form = $this->createForm(TimeCreditType::class, $credit, [
            'entreprise_choices' => [$credit->getEntreprise()],
            'category_choices' => $categoryRepository->findAllOrdered(),
            'allow_archive_field' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($credit->getTotalMinutes() !== $originalTotal) {
                $this->addFlash('error', 'Le total initial ne peut pas être modifié en édition.');
                $credit->setTotalMinutes($originalTotal);
                $credit->setRemainingMinutes($originalRemaining);

                return $this->render('time_credit/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier le crédit temps',
                    'edit_mode' => true,
                ]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Crédit temps mis à jour.');

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('time_credit/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le crédit temps',
            'edit_mode' => true,
        ]);
    }

    #[Route('/{id}/archiver', name: 'time_credit_archive', methods: ['POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function archive(Request $request, TimeCredit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TimeCreditVoter::MANAGE, $credit);

        if (!$this->isCsrfTokenValid('archive_time_credit'.$credit->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($credit->isArchived()) {
            $this->addFlash('success', 'Ce crédit temps est déjà archivé.');

            return $this->redirectToRoute('time_credit_index');
        }

        $credit->setArchived(true);
        $entityManager->flush();
        $this->addFlash('success', 'Crédit temps archivé.');

        return $this->redirectToRoute('time_credit_index');
    }

    #[Route('/{id}/supprimer', name: 'time_credit_delete', methods: ['POST'])]
    #[IsGranted('ROLE_17B_ADMIN')]
    public function delete(Request $request, TimeCredit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TimeCreditVoter::MANAGE, $credit);

        if (!$this->isCsrfTokenValid('delete_time_credit'.$credit->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if ($this->isCreditStarted($credit)) {
            $this->addFlash('error', 'Ce crédit a déjà été entamé et ne peut pas être supprimé.');

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        $entityManager->remove($credit);
        $entityManager->flush();
        $this->addFlash('success', 'Crédit temps supprimé.');

        return $this->redirectToRoute('time_credit_index');
    }

    #[Route('/{id}/intervention', name: 'time_credit_intervention_new', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function newIntervention(Request $request, TimeCredit $credit, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TimeCreditVoter::INTERVENE, $credit);

        $form = $this->createForm(TimeCreditInterventionType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actor = $this->getUser();
            if (!$actor instanceof User) {
                throw $this->createAccessDeniedException();
            }

            $duration = (int) $form->get('durationMinutes')->getData();
            if ($duration <= 0) {
                $this->addFlash('error', 'La durée doit être supérieure à 0 minute.');

                return $this->render('time_credit/intervention_form.html.twig', [
                    'form' => $form,
                    'credit' => $credit,
                ]);
            }

            if ($duration > $credit->getRemainingMinutes()) {
                $this->addFlash('error', 'La durée dépasse le solde disponible du crédit.');

                return $this->render('time_credit/intervention_form.html.twig', [
                    'form' => $form,
                    'credit' => $credit,
                ]);
            }

            /** @var \DateTimeImmutable $occurredAt */
            $occurredAt = $form->get('occurredAt')->getData();
            $description = (string) $form->get('description')->getData();

            $movement = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($actor)
                ->setType(TimeCreditMovement::TYPE_INTERVENTION)
                ->setDeltaMinutes(-$duration)
                ->setDescription($description)
                ->setOccurredAt($occurredAt);

            $credit->setRemainingMinutes($credit->getRemainingMinutes() - $duration);
            if ($credit->getRemainingMinutes() <= 0) {
                $credit->setArchived(true);
            }
            $credit->addMovement($movement);
            $entityManager->flush();
            $this->addFlash('success', 'Intervention enregistrée.');

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('time_credit/intervention_form.html.twig', [
            'form' => $form,
            'credit' => $credit,
        ]);
    }

    /**
     * @return list<Entreprise>
     */
    private function allowedClientEntreprises(User $actor, EntrepriseRepository $entrepriseRepository): array
    {
        if ($actor->is17bAdmin()) {
            return $entrepriseRepository->findNonAgencyOrdered();
        }

        if ($actor->is17bUser()) {
            return $entrepriseRepository->findNonAgencyByIdsOrdered($actor->getManagedEntrepriseIds());
        }

        return [];
    }

    private function canActOnEntreprise(User $actor, Entreprise $entreprise): bool
    {
        if ($actor->is17bAdmin()) {
            return !$entreprise->isAgency();
        }

        if ($actor->is17bUser()) {
            return $actor->managesEntreprise($entreprise);
        }

        return false;
    }

    private function isCreditStarted(TimeCredit $credit): bool
    {
        return $credit->getRemainingMinutes() < $credit->getTotalMinutes();
    }

    private function resolveReturnPath(string $path): string
    {
        if ($path !== '' && str_starts_with($path, '/')) {
            return $path;
        }

        return $this->generateUrl('time_credit_index');
    }
}
