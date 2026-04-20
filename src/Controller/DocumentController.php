<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\DocumentCategoryRepository;
use App\Repository\DocumentRepository;
use App\Security\Voter\DocumentVoter;
use App\Tenant\ManagedClientContext;
use App\Storage\DocumentStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/documents')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'document_index', methods: ['GET'])]
    public function index(
        DocumentRepository $documentRepository,
        DocumentCategoryRepository $categoryRepository,
        ManagedClientContext $managedClientContext,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $forcedEntreprise = null;
        if ($user->is17bStaff()) {
            $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
            if ($user->is17bUser() && !$forcedEntreprise instanceof Entreprise) {
                $this->addFlash('error', 'Sélectionne d’abord un client depuis le tableau de bord.');

                return $this->redirectToRoute('app_home');
            }
        }

        $documents = $documentRepository->findAccessibleForUser($user, $forcedEntreprise);

        /** @var array<int|string, list<Document>> $documentsByCategory */
        $documentsByCategory = [];
        foreach ($documents as $document) {
            $key = $document->getCategory()?->getId() ?? 'uncategorized';
            $documentsByCategory[$key] ??= [];
            $documentsByCategory[$key][] = $document;
        }

        return $this->render('document/index.html.twig', [
            'documents' => $documents,
            'documents_by_category' => $documentsByCategory,
            'category_roots' => $forcedEntreprise instanceof Entreprise
                ? $categoryRepository->findRootsForEntreprise($forcedEntreprise)
                : $categoryRepository->findRoots(),
            'can_upload' => $this->isGranted('ROLE_17B_ADMIN')
                || ($this->isGranted('ROLE_17B_USER') && $forcedEntreprise instanceof Entreprise),
            'show_client_in_tree' => !$this->isGranted('ROLE_CUSTOMER'),
        ]);
    }

    #[Route('/{id}/download', name: 'document_download', methods: ['GET'])]
    public function download(Document $document, DocumentStorage $storage): Response
    {
        $this->denyAccessUnlessGranted(DocumentVoter::DOWNLOAD, $document);

        if ($document->isExternalLink()) {
            $target = (string) $document->getExternalUrl();
            $parsed = parse_url($target);
            $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
            if (!\in_array($scheme, ['http', 'https'], true)) {
                throw $this->createNotFoundException();
            }

            return new RedirectResponse($target);
        }

        $absolutePath = $storage->resolveAbsolutePath($document);
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $document->getOriginalName() ?: $document->getTitle()
        );
        $mime = $document->getMimeType() ?: 'application/octet-stream';
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
