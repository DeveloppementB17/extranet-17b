<?php

namespace App\Controller\Admin;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\Admin\AdminEntrepriseType;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/entreprises')]
#[IsGranted('ROLE_17B_ADMIN')]
final class AdminEntrepriseController extends AbstractController
{
    #[Route('', name: 'admin_entreprise_index', methods: ['GET'])]
    public function index(EntrepriseRepository $entrepriseRepository): Response
    {
        return $this->render('admin/entreprise/index.html.twig', [
            'entreprises' => $entrepriseRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'admin_entreprise_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $entreprise = new Entreprise();
        $form = $this->createForm(AdminEntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise->setSlug(mb_strtolower($entreprise->getSlug()));
            $entityManager->persist($entreprise);
            $entityManager->flush();
            $this->addFlash('success', 'Entreprise créée.');

            return $this->redirectToRoute('admin_entreprise_index');
        }

        return $this->render('admin/entreprise/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle entreprise',
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_entreprise_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Entreprise $entreprise, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AdminEntrepriseType::class, $entreprise);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise->setSlug(mb_strtolower($entreprise->getSlug()));
            $entityManager->flush();
            $this->addFlash('success', 'Entreprise mise à jour.');

            return $this->redirectToRoute('admin_entreprise_index');
        }

        return $this->render('admin/entreprise/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l’entreprise',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_entreprise_delete', methods: ['POST'])]
    public function delete(Request $request, Entreprise $entreprise, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_entreprise'.$entreprise->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $nUsers = $entityManager->getRepository(User::class)->count(['entreprise' => $entreprise]);
        if ($nUsers > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des utilisateurs sont encore rattachés à cette entreprise.');

            return $this->redirectToRoute('admin_entreprise_index');
        }

        $nDocs = $entityManager->getRepository(Document::class)->count(['entreprise' => $entreprise]);
        if ($nDocs > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des documents sont encore associés à cette entreprise.');

            return $this->redirectToRoute('admin_entreprise_index');
        }

        $nCat = $entityManager->getRepository(DocumentCategory::class)->count(['entreprise' => $entreprise]);
        if ($nCat > 0) {
            $this->addFlash('error', 'Impossible de supprimer : des dossiers documents existent encore pour cette entreprise.');

            return $this->redirectToRoute('admin_entreprise_index');
        }

        $entityManager->remove($entreprise);
        $entityManager->flush();
        $this->addFlash('success', 'Entreprise supprimée.');

        return $this->redirectToRoute('admin_entreprise_index');
    }
}
