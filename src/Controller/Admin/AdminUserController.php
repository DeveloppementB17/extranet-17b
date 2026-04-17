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
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $userRepository->findAllForAdminOrdered(),
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
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primaryRole = (string) $form->get('primaryRole')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $form->get('managedEntreprises')->getData() ?? [];

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
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $primaryRole = (string) $form->get('primaryRole')->getData();
            /** @var list<Entreprise> $managed */
            $managed = $form->get('managedEntreprises')->getData() ?? [];

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

        if ($primaryRole === 'ROLE_17B_USER') {
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
        if ($primaryRole === 'ROLE_17B_USER') {
            foreach ($managed as $entreprise) {
                $user->addManagedEntreprise($entreprise);
            }
        }
    }
}
