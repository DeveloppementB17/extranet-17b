<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\Admin\AdminUserType;
use App\Repository\EntrepriseRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function index(Request $request, UserRepository $userRepository): Response
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
        ]);
    }

    #[Route('/nouveau', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminUserType::class, $user, [
            'require_password' => true,
            'entreprise_choices' => $entrepriseRepository->findAllOrdered(),
            'client_entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(),
            'primary_role_data' => 'ROLE_CUSTOMER_USER',
            'managed_entreprises_data' => [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primaryRole = (string) $form->get('primaryRole')->getData();
            /** @var iterable<Entreprise>|null $managedRaw */
            $managedRaw = $form->get('managedEntreprises')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $managedRaw === null ? [] : array_values(iterator_to_array($managedRaw));

            $err = $this->validateRoleEntreprise($user, $primaryRole, $managed);
            if ($err !== null) {
                $this->addFlash('error', $err);

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvel utilisateur',
                ]);
            }

            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvel utilisateur',
                ]);
            }

            $this->applyRoleAndManaged($user, $primaryRole, $managed);
            $user->setPassword($passwordHasher->hashPassword($user, $plain));

            $entityManager->persist($user);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvel utilisateur',
                ]);
            }

            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel utilisateur',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
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
            /** @var iterable<Entreprise>|null $managedRaw */
            $managedRaw = $form->get('managedEntreprises')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $managedRaw === null ? [] : array_values(iterator_to_array($managedRaw));

            $err = $this->validateRoleEntreprise($user, $primaryRole, $managed);
            if ($err !== null) {
                $this->addFlash('error', $err);

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier l’utilisateur',
                    'edit_user' => $user,
                ]);
            }

            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier l’utilisateur',
                    'edit_user' => $user,
                ]);
            }

            $this->applyRoleAndManaged($user, $primaryRole, $managed);
            if ($plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->render('admin/user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier l’utilisateur',
                    'edit_user' => $user,
                ]);
            }

            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l’utilisateur',
            'edit_user' => $user,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_user'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $actor = $this->getUser();
        if (!$actor instanceof User || $actor->getId() === $user->getId()) {
            $this->addFlash('error', 'Tu ne peux pas supprimer ton propre compte depuis cette interface.');

            return $this->redirectToRoute('admin_user_index');
        }

        $docCount = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.client = :u OR d.uploadedBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
        if ($docCount > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des documents référencent encore cet utilisateur.');

            return $this->redirectToRoute('admin_user_index');
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

        if ($primaryRole === 'ROLE_17B_ADMIN' || $primaryRole === 'ROLE_17B_USER') {
            foreach ($managed as $e) {
                if ($e->isAgency()) {
                    return 'Les entreprises gérées doivent être des entreprises clientes.';
                }
            }
        }

        return null;
    }

    /**
     * @param list<Entreprise> $managed
     */
    private function applyRoleAndManaged(User $user, string $primaryRole, array $managed): void
    {
        $user->setRoles([$primaryRole]);
        $user->clearManagedEntreprises();
        if ($primaryRole === 'ROLE_17B_ADMIN' || $primaryRole === 'ROLE_17B_USER') {
            foreach ($managed as $entreprise) {
                $user->addManagedEntreprise($entreprise);
            }
        }
    }
}
