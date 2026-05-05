<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\DocumentEditType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\DocumentRepository;
use App\Repository\EntrepriseRepository;
use App\Security\Voter\DocumentVoter;
use App\Tenant\ManagedClientContext;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/documents')]
final class DocumentController extends AbstractController
{
    #[Route('', name: 'document_index', methods: ['GET'])]
    public function index(
        Request $request,
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
            if ($user->is17bUser() && !$forcedEntreprise instanceof Entreprise && $user->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à ton compte.');

                return $this->redirectToRoute('app_home');
            }
        }

        $documents = $documentRepository->findAccessibleForUser($user, $forcedEntreprise);
        $availableEntreprises = [];
        $availableCategories = [];
        foreach ($documents as $document) {
            $entreprise = $document->getEntreprise();
            if ($entreprise !== null) {
                $availableEntreprises[$entreprise->getId() ?? 0] = $entreprise->getName();
            }
            $category = $document->getCategory();
            if ($category !== null) {
                $availableCategories[$category->getId() ?? 0] = $category->getName();
            }
        }
        asort($availableEntreprises, SORT_NATURAL | SORT_FLAG_CASE);
        asort($availableCategories, SORT_NATURAL | SORT_FLAG_CASE);
        $isAdminListView = $user->is17bStaff() || $user->isCustomerActor();
        if ($isAdminListView) {
            $search = trim((string) $request->query->get('q', ''));
            $entrepriseFilter = (int) $request->query->get('entreprise', 0);
            $categoryFilter = (int) $request->query->get('category', 0);
            $sort = (string) $request->query->get('sort', 'created_at');
            $direction = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $allowedSorts = ['created_at', 'title', 'entreprise', 'category'];
            if (!\in_array($sort, $allowedSorts, true)) {
                $sort = 'created_at';
            }

            $documents = array_values(array_filter($documents, static function (Document $document) use (
                $search,
                $entrepriseFilter,
                $categoryFilter,
            ): bool {
                if ($entrepriseFilter > 0 && $document->getEntreprise()?->getId() !== $entrepriseFilter) {
                    return false;
                }
                if ($categoryFilter > 0 && $document->getCategory()?->getId() !== $categoryFilter) {
                    return false;
                }
                if ($search === '') {
                    return true;
                }

                $haystack = mb_strtolower(implode(' ', array_filter([
                    $document->getTitle(),
                    $document->getOriginalName(),
                    $document->getEntreprise()?->getName(),
                    $document->getCategory()?->getName(),
                ])));

                return str_contains($haystack, mb_strtolower($search));
            }));

            usort($documents, static function (Document $left, Document $right) use ($sort, $direction): int {
                $result = match ($sort) {
                    'title' => strcasecmp($left->getTitle(), $right->getTitle()),
                    'entreprise' => strcasecmp((string) $left->getEntreprise()?->getName(), (string) $right->getEntreprise()?->getName()),
                    'category' => strcasecmp((string) $left->getCategory()?->getName(), (string) $right->getCategory()?->getName()),
                    default => $left->getCreatedAt() <=> $right->getCreatedAt(),
                };

                return $direction === 'asc' ? $result : -$result;
            });
        }

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
            'category_roots' => $categoryRepository->findRoots(),
            'is_admin_list_view' => $isAdminListView,
            'can_upload' => $this->isGranted('ROLE_17B_ADMIN')
                || ($this->isGranted('ROLE_17B_USER') && $user->getManagedEntrepriseIds() !== []),
            'show_client_in_tree' => !$this->isGranted('ROLE_CUSTOMER'),
            'search_query' => $isAdminListView ? trim((string) $request->query->get('q', '')) : '',
            'filter_entreprise' => $isAdminListView ? (int) $request->query->get('entreprise', 0) : 0,
            'filter_category' => $isAdminListView ? (int) $request->query->get('category', 0) : 0,
            'sort_field' => $isAdminListView ? (string) $request->query->get('sort', 'created_at') : 'created_at',
            'sort_direction' => $isAdminListView && strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            'available_entreprises' => $availableEntreprises,
            'available_categories' => $availableCategories,
        ]);
    }

    #[Route('/{id}/edit', name: 'document_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Document $document,
        DocumentCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(DocumentVoter::MANAGE, $document);

        $user = $this->getUser();
        if (!$user instanceof User || !$user->is17bStaff()) {
            throw $this->createAccessDeniedException();
        }

        $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
        $allowedEntreprises = ($forcedEntreprise instanceof Entreprise && $user->is17bUser())
            ? [$forcedEntreprise]
            : $this->allowedClientEntreprises($user, $entrepriseRepository);
        if ($allowedEntreprises === []) {
            $this->addFlash('error', 'Aucune entreprise cliente disponible.');

            return $this->redirectToRoute('document_index');
        }

        $form = $this->createForm(DocumentEditType::class, $document, [
            'category_choices' => $this->buildCategoryChoices($categoryRepository),
            'entreprise_choices' => $allowedEntreprises,
            'lock_entreprise' => $forcedEntreprise instanceof Entreprise && $user->is17bUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise = $document->getEntreprise();
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
                $this->addFlash('error', 'Choisis une entreprise cliente valide.');

                return $this->redirectToRoute('document_edit', ['id' => $document->getId()]);
            }

            if ($user->is17bUser() && !$user->managesEntreprise($entreprise)) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->flush();
            $this->addFlash('success', 'Document mis à jour.');

            return $this->redirectToRoute('document_index');
        }

        return $this->render('document/form.html.twig', [
            'form' => $form,
            'document' => $document,
            'title' => 'Modifier le document',
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

    #[Route('/{id}/delete', name: 'document_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Document $document,
        DocumentStorage $storage,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(DocumentVoter::MANAGE, $document);

        if (!$this->isCsrfTokenValid('delete_document'.$document->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$document->isExternalLink()) {
            try {
                $absolutePath = $storage->resolveAbsolutePath($document);
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
            } catch (\InvalidArgumentException) {
                // Rien à supprimer côté disque (données incomplètes/lien externe).
            }
        }

        $entityManager->remove($document);
        $entityManager->flush();
        $this->addFlash('success', 'Document supprimé.');

        return $this->redirectToRoute('document_index');
    }

    /**
     * @return array<string, \App\Entity\DocumentCategory>
     */
    private function buildCategoryChoices(
        DocumentCategoryRepository $categoryRepository,
    ): array {
        $choices = [];
        foreach ($categoryRepository->findRoots() as $root) {
            $this->appendCategoryBranchChoices($root, '', $choices);
        }

        return $choices;
    }

    /**
     * @param array<string, \App\Entity\DocumentCategory> $choices
     */
    private function appendCategoryBranchChoices(
        \App\Entity\DocumentCategory $category,
        string $path,
        array &$choices,
    ): void {
        $label = $path.$category->getName();
        $choices[$label] = $category;
        $next = $path === '' ? $category->getName().' / ' : $path.$category->getName().' / ';
        foreach ($category->getChildren() as $child) {
            $this->appendCategoryBranchChoices($child, $next, $choices);
        }
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
}
