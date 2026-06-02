<?php

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\Admin\AdminUserType;
use App\Repository\EntrepriseRepository;
use App\Repository\UserRepository;
use App\Service\UserReferenceReassignment;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_17B_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_user_index', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $userRepository,
        EntrepriseRepository $entrepriseRepository,
    ): Response
    {
        $users = $userRepository->findAllForAdminOrdered();
        $search = trim((string) $request->query->get('q', ''));
        $roleFilter = (string) $request->query->get('role', 'all');
        $entrepriseFilter = (int) $request->query->get('entreprise', 0);
        $sort = (string) $request->query->get('sort', 'email');
        $direction = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['email', 'role', 'entreprise'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'email';
        }
        if (!\in_array($roleFilter, ['all', ...User::assignableRoleValues()], true)) {
            $roleFilter = 'all';
        }

        $availableEntreprises = [];
        foreach ($users as $u) {
            $e = $u->getEntreprise();
            if ($e !== null && $e->getId() !== null) {
                $availableEntreprises[$e->getId()] = $e->getName();
            }
        }
        asort($availableEntreprises, SORT_NATURAL | SORT_FLAG_CASE);

        $users = array_values(array_filter($users, static function (User $user) use ($search, $roleFilter, $entrepriseFilter): bool {
            if ($roleFilter !== 'all' && $user->getPrimaryStoredRole() !== $roleFilter) {
                return false;
            }
            if ($entrepriseFilter > 0 && $user->getEntreprise()?->getId() !== $entrepriseFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', array_filter([
                $user->getEmail(),
                $user->getEntreprise()?->getName(),
                $user->getPrimaryStoredRole(),
            ])));

            return str_contains($haystack, mb_strtolower($search));
        }));

        usort($users, static function (User $left, User $right) use ($sort, $direction): int {
            $result = match ($sort) {
                'role' => strcasecmp($left->getPrimaryStoredRole(), $right->getPrimaryStoredRole()),
                'entreprise' => strcasecmp((string) $left->getEntreprise()?->getName(), (string) $right->getEntreprise()?->getName()),
                default => strcasecmp($left->getEmail(), $right->getEmail()),
            };

            return $direction === 'asc' ? $result : -$result;
        });

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
            'search_query' => $search,
            'filter_role' => $roleFilter,
            'filter_entreprise' => $entrepriseFilter,
            'sort_field' => $sort,
            'sort_direction' => $direction,
            'available_entreprises' => $availableEntreprises,
            'available_roles' => User::assignableRoleValues(),
            'client_entreprises' => $entrepriseRepository->findNonAgencyOrdered(),
        ]);
    }

    #[Route('/actions-masse', name: 'admin_user_bulk_update', methods: ['POST'])]
    public function bulkUpdate(
        Request $request,
        UserRepository $userRepository,
        EntrepriseRepository $entrepriseRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('admin_user_bulk_update', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        /** @var list<string> $selectedRaw */
        $selectedRaw = array_values($request->request->all('selected_ids'));
        $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedRaw), static fn (int $id): bool => $id > 0)));
        if ($selectedIds === []) {
            $this->addFlash('error', 'Sélectionnez au moins un utilisateur.');

            return $this->redirectToRoute('admin_user_index', $request->query->all());
        }

        $action = (string) $request->request->get('bulk_action', '');
        $users = $userRepository->findBy(['id' => $selectedIds]);
        if ($users === []) {
            $this->addFlash('error', 'Aucun utilisateur trouvé pour la sélection.');

            return $this->redirectToRoute('admin_user_index', $request->query->all());
        }

        $updated = 0;
        $skipped = 0;

        if ($action === 'assign_managed_entreprise') {
            $entrepriseId = (int) $request->request->get('managed_entreprise_id', 0);
            $entreprise = $entrepriseRepository->find($entrepriseId);
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
                $this->addFlash('error', 'Entreprise cliente invalide pour l’attribution.');

                return $this->redirectToRoute('admin_user_index', $request->query->all());
            }

            foreach ($users as $user) {
                if (!$user->is17bUser()) {
                    ++$skipped;
                    continue;
                }
                $before = \count($user->getManagedEntrepriseIds());
                $user->addManagedEntreprise($entreprise);
                $after = \count($user->getManagedEntrepriseIds());
                if ($after > $before) {
                    ++$updated;
                } else {
                    ++$skipped;
                }
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Attribution entreprise 17b effectuée (%d modifiés, %d ignorés).',
                $updated,
                $skipped
            ));

            return $this->redirectToRoute('admin_user_index', $request->query->all());
        }

        if ($action === 'set_client_role') {
            $role = (string) $request->request->get('client_role', '');
            if (!\in_array($role, ['ROLE_CUSTOMER_ADMIN', 'ROLE_CUSTOMER_USER'], true)) {
                $this->addFlash('error', 'Rôle client invalide.');

                return $this->redirectToRoute('admin_user_index', $request->query->all());
            }

            foreach ($users as $user) {
                if (!$user->isCustomerActor()) {
                    ++$skipped;
                    continue;
                }
                $user->setRoles([$role]);
                ++$updated;
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Rôle client mis à jour (%d modifiés, %d ignorés).',
                $updated,
                $skipped
            ));

            return $this->redirectToRoute('admin_user_index', $request->query->all());
        }

        if ($action === 'set_client_entreprise') {
            $entrepriseId = (int) $request->request->get('client_entreprise_id', 0);
            $entreprise = $entrepriseRepository->find($entrepriseId);
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
                $this->addFlash('error', 'Entreprise cliente invalide.');

                return $this->redirectToRoute('admin_user_index', $request->query->all());
            }

            foreach ($users as $user) {
                if (!$user->isCustomerActor()) {
                    ++$skipped;
                    continue;
                }
                $user->setEntreprise($entreprise);
                ++$updated;
            }

            $entityManager->flush();
            $this->addFlash('success', sprintf(
                'Société cliente mise à jour (%d modifiés, %d ignorés).',
                $updated,
                $skipped
            ));

            return $this->redirectToRoute('admin_user_index', $request->query->all());
        }

        $this->addFlash('error', 'Action en masse inconnue.');

        return $this->redirectToRoute('admin_user_index', $request->query->all());
    }

    #[Route('/nouveau', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $clientEntreprises = $entrepriseRepository->findNonAgencyOrdered();
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, [
            'require_password' => true,
            'entreprise_choices' => $entrepriseRepository->findAllOrdered(),
            'client_entreprise_choices' => $clientEntreprises,
            'primary_role_data' => 'ROLE_CUSTOMER_USER',
            'managed_entreprises_data' => [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primaryRole = (string) $form->get('primaryRole')->getData();
            $this->applyAgencyEntrepriseFor17bStaff($user, $primaryRole, $entrepriseRepository);
            /** @var iterable<Entreprise>|null $managedRaw */
            $managedRaw = $form->get('managedEntreprises')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $managedRaw === null ? [] : array_values(iterator_to_array($managedRaw));

            $err = $this->validateRoleEntreprise($user, $primaryRole, $managed);
            if ($err !== null) {
                $this->addFlash('error', $err);

                return $this->renderUserForm($form, 'Nouvel utilisateur', $entrepriseRepository);
            }

            if (!$this->isEmailAvailableForUser($userRepository, (string) $form->get('email')->getData(), null)) {
                $message = 'Cet email est déjà utilisé.';
                $form->get('email')->addError(new FormError($message));
                $this->addFlash('error', $message);

                return $this->renderUserForm($form, 'Nouvel utilisateur', $entrepriseRepository);
            }

            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->renderUserForm($form, 'Nouvel utilisateur', $entrepriseRepository);
            }

            $this->applyRoleAndManaged($user, $primaryRole, $managed);
            $user->setPassword($passwordHasher->hashPassword($user, $plain));

            $entityManager->persist($user);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->renderUserForm($form, 'Nouvel utilisateur', $entrepriseRepository);
            }

            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->renderUserForm($form, 'Nouvel utilisateur', $entrepriseRepository);
    }

    #[Route('/{id}/modifier', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(AdminUserType::class, $user, [
            'require_password' => false,
            'entreprise_choices' => $entrepriseRepository->findAllOrdered(),
            'client_entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(),
            'primary_role_data' => $user->getPrimaryStoredRole(),
            'managed_entreprises_data' => $user->getManagedEntreprises()->toArray(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primaryRole = (string) $form->get('primaryRole')->getData();
            $this->applyAgencyEntrepriseFor17bStaff($user, $primaryRole, $entrepriseRepository);
            /** @var iterable<Entreprise>|null $managedRaw */
            $managedRaw = $form->get('managedEntreprises')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $managedRaw === null ? [] : array_values(iterator_to_array($managedRaw));

            $err = $this->validateRoleEntreprise($user, $primaryRole, $managed);
            if ($err !== null) {
                $this->addFlash('error', $err);

                return $this->renderUserForm($form, 'Modifier l’utilisateur', $entrepriseRepository, $user);
            }

            if (!$this->isEmailAvailableForUser($userRepository, (string) $form->get('email')->getData(), $user->getId())) {
                $message = 'Cet email est déjà utilisé.';
                $form->get('email')->addError(new FormError($message));
                $this->addFlash('error', $message);

                return $this->renderUserForm($form, 'Modifier l’utilisateur', $entrepriseRepository, $user);
            }

            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->renderUserForm($form, 'Modifier l’utilisateur', $entrepriseRepository, $user);
            }

            $this->applyRoleAndManaged($user, $primaryRole, $managed);
            if ($plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->renderUserForm($form, 'Modifier l’utilisateur', $entrepriseRepository, $user);
            }

            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->renderUserForm($form, 'Modifier l’utilisateur', $entrepriseRepository, $user);
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete_confirm', methods: ['GET'])]
    public function deleteConfirm(
        User $user,
        UserRepository $userRepository,
        UserReferenceReassignment $referenceReassignment,
        EntityManagerInterface $entityManager,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User || $actor->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cette interface.');

            return $this->redirectToRoute('admin_user_index');
        }

        $requiresSuccessor = $referenceReassignment->requiresSuccessor($user, $entityManager);
        $successors = $userRepository->find17bStaffExcluding($user);

        if ($requiresSuccessor && $successors === []) {
            $this->addFlash(
                'error',
                'Impossible de supprimer cet utilisateur : aucun autre compte équipe 17b n’est disponible pour reprendre ses documents ou crédits temps.',
            );

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/delete_confirm.html.twig', [
            'user' => $user,
            'requires_successor' => $requiresSuccessor,
            'document_count' => $referenceReassignment->countDocumentReferences($user, $entityManager),
            'uploaded_document_count' => $referenceReassignment->countUploadedDocuments($user, $entityManager),
            'time_credit_reference_count' => $referenceReassignment->countTimeCreditReferences($user, $entityManager),
            'successors' => $successors,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        UserRepository $userRepository,
        UserReferenceReassignment $referenceReassignment,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_user'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $actor = $this->getUser();
        if (!$actor instanceof User || $actor->getId() === $user->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte depuis cette interface.');

            return $this->redirectToRoute('admin_user_index');
        }

        if ($referenceReassignment->requiresSuccessor($user, $entityManager)) {
            $successorId = (int) $request->request->get('successor_id', 0);
            $successor = $successorId > 0 ? $userRepository->find($successorId) : null;
            if (!$successor instanceof User || !$successor->is17bStaff()) {
                $this->addFlash('error', 'Choisissez un compte équipe 17b pour reprendre les documents et crédits temps.');

                return $this->redirectToRoute('admin_user_delete_confirm', ['id' => $user->getId()]);
            }

            $referenceReassignment->reassign($user, $successor, $entityManager);
        }

        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('admin_user_index');
    }

    /**
     * @param list<Entreprise> $managed
     */
    private function validateRoleEntreprise(User $user, string $primaryRole, array $managed): ?string
    {
        if (!\in_array($primaryRole, User::assignableRoleValues(), true)) {
            return 'Rôle invalide.';
        }

        $entreprise = $user->getEntreprise();
        if ($entreprise === null) {
            return 'Entreprise de rattachement manquante.';
        }

        if ($primaryRole === 'ROLE_17B_ADMIN' || $primaryRole === 'ROLE_17B_USER') {
            if (!$entreprise->isAgency()) {
                return 'Les comptes équipe 17b doivent être rattachés à une entreprise marquée « agence ».';
            }
        } else {
            if ($entreprise->isAgency()) {
                return 'Les comptes clients doivent être rattachés à une entreprise cliente (pas une agence).';
            }
        }

        if ($primaryRole === 'ROLE_17B_USER') {
            foreach ($managed as $e) {
                if ($e->isAgency()) {
                    return 'Les entreprises gérées doivent être des entreprises clientes.';
                }
            }
        }

        return null;
    }

    private function applyAgencyEntrepriseFor17bStaff(User $user, string $primaryRole, EntrepriseRepository $entrepriseRepository): void
    {
        if ($primaryRole !== 'ROLE_17B_ADMIN' && $primaryRole !== 'ROLE_17B_USER') {
            return;
        }

        $agency = $this->resolveAgencyEntreprise($entrepriseRepository);
        if ($agency !== null) {
            $user->setEntreprise($agency);
        }
    }

    private function resolveAgencyEntreprise(EntrepriseRepository $entrepriseRepository): ?Entreprise
    {
        $agencies = $entrepriseRepository->findAgenciesOrdered();

        return $agencies[0] ?? null;
    }

    private function renderUserForm(
        FormInterface $form,
        string $title,
        EntrepriseRepository $entrepriseRepository,
        ?User $editUser = null,
    ): Response {
        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => $title,
            'edit_user' => $editUser,
            'agency_entreprise_id' => $this->resolveAgencyEntreprise($entrepriseRepository)?->getId(),
        ]);
    }

    /**
     * @param list<Entreprise> $managed
     */
    private function applyRoleAndManaged(User $user, string $primaryRole, array $managed): void
    {
        $user->setRoles([$primaryRole]);
        $user->clearManagedEntreprises();
        if ($primaryRole === 'ROLE_17B_USER') {
            foreach ($managed as $entreprise) {
                $user->addManagedEntreprise($entreprise);
            }
        }
    }

    private function isEmailAvailableForUser(UserRepository $userRepository, string $email, ?int $currentUserId): bool
    {
        $existing = $userRepository->findOneBy(['email' => mb_strtolower(trim($email))]);
        if (!$existing instanceof User) {
            return true;
        }

        return $currentUserId !== null && $existing->getId() === $currentUserId;
    }
}
