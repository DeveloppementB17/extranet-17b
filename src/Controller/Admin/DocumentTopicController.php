<?php

namespace App\Controller\Admin;

use App\Entity\DocumentTopic;
use App\Form\DocumentTopicType;
use App\Repository\DocumentTopicRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/documents/sujets')]
#[IsGranted('ROLE_17B_ADMIN')]
final class DocumentTopicController extends AbstractController
{
    #[Route('', name: 'document_topic_index', methods: ['GET'])]
    public function index(DocumentTopicRepository $topicRepository): Response
    {
        return $this->render('document_topic/index.html.twig', [
            'topics' => $topicRepository->findAllOrdered(),
        ]);
    }

    #[Route('/nouveau', name: 'document_topic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $topic = new DocumentTopic();
        $form = $this->createForm(DocumentTopicType::class, $topic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($topic);
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Ce sujet existe déjà.');

                return $this->render('document_topic/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouveau sujet',
                ]);
            }
            $this->addFlash('success', 'Sujet créé.');

            return $this->redirectToRoute('document_topic_index');
        }

        return $this->render('document_topic/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau sujet',
        ]);
    }

    #[Route('/{id}/modifier', name: 'document_topic_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DocumentTopic $topic, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DocumentTopicType::class, $topic);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $this->addFlash('error', 'Ce sujet existe déjà.');

                return $this->render('document_topic/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier le sujet',
                ]);
            }
            $this->addFlash('success', 'Sujet mis à jour.');

            return $this->redirectToRoute('document_topic_index');
        }

        return $this->render('document_topic/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le sujet',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'document_topic_delete', methods: ['POST'])]
    public function delete(Request $request, DocumentTopic $topic, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete_document_topic'.$topic->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$topic->getDocuments()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer : ce sujet est utilisé par des documents.');

            return $this->redirectToRoute('document_topic_index');
        }

        $entityManager->remove($topic);
        $entityManager->flush();
        $this->addFlash('success', 'Sujet supprimé.');

        return $this->redirectToRoute('document_topic_index');
    }
}
