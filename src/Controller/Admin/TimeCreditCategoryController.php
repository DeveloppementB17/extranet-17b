<?php

namespace App\Controller\Admin;

use App\Entity\TimeCreditCategory;
use App\Form\TimeCreditCategoryType;
use App\Repository\TimeCreditCategoryRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/credits/categories')]
#[IsGranted('ROLE_17B_ADMIN')]
final class TimeCreditCategoryController extends AbstractController
{
    #[Route('', name: 'time_credit_category_index', methods: ['GET'])]
    public function index(TimeCreditCategoryRepository $categoryRepository): Response
    {
        return $this->render('time_credit_category/index.html.twig', [
            'categories' => $categoryRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouvelle', name: 'time_credit_category_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new TimeCreditCategory();
        $form = $this->createForm(TimeCreditCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cette catégorie existe déjà.');

                return $this->render('time_credit_category/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouvelle catégorie',
                ]);
            }
            $this->addFlash('success', 'Catégorie créée.');

            return $this->redirectToRoute('time_credit_category_index');
        }

        return $this->render('time_credit_category/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle catégorie',
        ]);
    }

    #[Route('/{id}/modifier', name: 'time_credit_category_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TimeCreditCategory $category, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TimeCreditCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Cette catégorie existe déjà.');

                return $this->render('time_credit_category/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier la catégorie',
                ]);
            }
            $this->addFlash('success', 'Catégorie mise à jour.');

            return $this->redirectToRoute('time_credit_category_index');
        }

        return $this->render('time_credit_category/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la catégorie',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'time_credit_category_delete', methods: ['POST'])]
    public function delete(Request $request, TimeCreditCategory $category, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_time_credit_category'.$category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$category->getTimeCredits()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer : cette catégorie est utilisée par des crédits temps.');

            return $this->redirectToRoute('time_credit_category_index');
        }

        $entityManager->remove($category);
        $entityManager->flush();
        $this->addFlash('success', 'Catégorie supprimée.');

        return $this->redirectToRoute('time_credit_category_index');
    }
}
