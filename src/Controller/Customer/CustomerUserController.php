<?php

namespace App\Controller\Customer;

use App\Entity\Document;
use App\Entity\User;
use App\Form\Customer\CustomerUserType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte/utilisateurs')]
#[IsGranted('ROLE_CUSTOMER_ADMIN')]
final class CustomerUserController extends AbstractController
{
    #[Route('', name: 'customer_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $actor = $this->getActor();
        $entrepriseId = $actor->getEntreprise()?->getId();
        if ($entrepriseId === null) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('customer_user/index.html.twig', [
            'users' => $userRepository->findCustomerUsersForEntreprise($entrepriseId),
        ]);
    }

    #[Route('/nouveau', name: 'customer_user_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $actor = $this->getActor();
        $entreprise = $actor->getEntreprise();
        if ($entreprise === null || $entreprise->isAgency()) {
            throw $this->createAccessDeniedException();
        }

        $user = (new User())
            ->setEntreprise($entreprise)
            ->setRoles(['ROLE_CUSTOMER_USER']);
        $user->clearManagedEntreprises();

        $form = $this->createForm(CustomerUserType::class, $user, [
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->render('customer_user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvel utilisateur client',
                ]);
            }

            $user->setRoles(['ROLE_CUSTOMER_USER']);
            $user->setEntreprise($entreprise);
            $user->clearManagedEntreprises();
            $user->setPassword($passwordHasher->hashPassword($user, $plain));

            $entityManager->persist($user);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->render('customer_user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvel utilisateur client',
                ]);
            }

            $this->addFlash('success', 'Utilisateur client créé.');

            return $this->redirectToRoute('customer_user_index');
        }

        return $this->render('customer_user/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel utilisateur client',
        ]);
    }

    #[Route('/{id}/modifier', name: 'customer_user_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $actor = $this->getActor();
        $this->denyCustomerUserAccess($actor, $user);

        $form = $this->createForm(CustomerUserType::class, $user, [
            'require_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '' && strlen($plain) < 8) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');

                return $this->render('customer_user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier un utilisateur client',
                ]);
            }

            // Sécurité défensive : même si le payload est altéré.
            $user->setRoles(['ROLE_CUSTOMER_USER']);
            $user->setEntreprise($actor->getEntreprise());

            if ($plain !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plain));
            }

            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->render('customer_user/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier un utilisateur client',
                ]);
            }

            $this->addFlash('success', 'Utilisateur client mis à jour.');

            return $this->redirectToRoute('customer_user_index');
        }

        return $this->render('customer_user/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier un utilisateur client',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'customer_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $actor = $this->getActor();
        $this->denyCustomerUserAccess($actor, $user);

        if (!$this->isCsrfTokenValid('delete_customer_user'.$user->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
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

            return $this->redirectToRoute('customer_user_index');
        }

        $entityManager->remove($user);
        $entityManager->flush();
        $this->addFlash('success', 'Utilisateur client supprimé.');

        return $this->redirectToRoute('customer_user_index');
    }

    private function getActor(): User
    {
        $actor = $this->getUser();
        if (!$actor instanceof User || !$actor->isCustomerAdmin()) {
            throw $this->createAccessDeniedException();
        }

        return $actor;
    }

    private function denyCustomerUserAccess(User $actor, User $subject): void
    {
        $actorEntreprise = $actor->getEntreprise();
        $subjectEntreprise = $subject->getEntreprise();

        if (!$subject->isCustomerUser()
            || $actorEntreprise === null
            || $subjectEntreprise === null
            || $actorEntreprise->getId() !== $subjectEntreprise->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
