<?php

namespace App\Controller\Admin;

use App\Entity\DocumentKind;
use App\Form\DocumentKindType;
use App\Repository\DocumentKindRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents/types')]
#[IsGranted('ROLE_17B_ADMIN')]
final class DocumentKindController extends AbstractController
{
    #[Route('', name: 'document_kind_index', methods: ['GET'])]
    public function index(DocumentKindRepository $kindRepository): Response
    {
        return $this->render('document_kind/index.html.twig', [
            'kinds' => $kindRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'document_kind_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $kind = new DocumentKind();
        $form = $this->createForm(DocumentKindType::class, $kind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($kind);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Ce type existe déjà.');

                return $this->render('document_kind/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouveau type',
                ]);
            }
            $this->addFlash('success', 'Type créé.');

            return $this->redirectToRoute('document_kind_index');
        }

        return $this->render('document_kind/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau type',
        ]);
    }

    #[Route('/{id}/modifier', name: 'document_kind_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentKind $kind, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentKindType::class, $kind);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Ce type existe déjà.');

                return $this->render('document_kind/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier le type',
                ]);
            }
            $this->addFlash('success', 'Type mis à jour.');

            return $this->redirectToRoute('document_kind_index');
        }

        return $this->render('document_kind/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le type',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'document_kind_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentKind $kind, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_document_kind'.$kind->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$kind->getDocuments()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer : ce type est utilisé par des documents.');

            return $this->redirectToRoute('document_kind_index');
        }

        $entityManager->remove($kind);
        $entityManager->flush();
        $this->addFlash('success', 'Type supprimé.');

        return $this->redirectToRoute('document_kind_index');
    }
}
