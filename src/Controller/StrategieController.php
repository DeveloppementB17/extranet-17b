<?php

namespace App\Controller;

use App\Document\DocumentMimeResolver;
use App\Document\DocumentUploadPolicy;
use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\DocumentKind;
use App\Entity\DocumentTopic;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\DocumentBatchUploadType;
use App\Form\DocumentEditType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\DocumentKindRepository;
use App\Repository\DocumentRepository;
use App\Repository\DocumentTopicRepository;
use App\Repository\EntrepriseRepository;
use App\Security\Voter\DocumentVoter;
use App\Storage\DocumentStorage;
use App\Tenant\ManagedClientContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/strategie')]
final class StrategieController extends AbstractController
{
    #[Route('', name: 'strategie_index', methods: ['GET'])]
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
        $forcedEntrepriseIds = null;
        if ($user->is17bStaff()) {
            $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
            $forcedEntrepriseIds = $managedClientContext->getForcedEntrepriseIds($user);
            if ($user->is17bUser() && !$forcedEntreprise instanceof Entreprise && $user->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à votre compte.');

                return $this->redirectToRoute('app_home');
            }
            if ($user->is17bAdmin() && $managedClientContext->isMineScope($user) && $user->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucun client rattaché. Configurez-les dans Mon compte.');

                return $this->redirectToRoute('app_account');
            }
        }

        $documents = $documentRepository->findAccessibleForUser($user, $forcedEntreprise, $forcedEntrepriseIds, 'strategie');
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
            $sort = (string) $request->query->get('sort', 'document_date');
            $direction = strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

            $allowedSorts = ['document_date', 'created_at', 'title', 'entreprise', 'category', 'year', 'kind', 'topic'];
            if (!\in_array($sort, $allowedSorts, true)) {
                $sort = 'document_date';
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
                    $document->getKind()?->getName(),
                    $document->getTopic()?->getName(),
                    $document->getYear() !== null ? (string) $document->getYear() : null,
                ])));

                return str_contains($haystack, mb_strtolower($search));
            }));

            usort($documents, static function (Document $left, Document $right) use ($sort, $direction): int {
                $result = match ($sort) {
                    'title' => strcasecmp($left->getTitle(), $right->getTitle()),
                    'entreprise' => strcasecmp((string) $left->getEntreprise()?->getName(), (string) $right->getEntreprise()?->getName()),
                    'category' => strcasecmp((string) $left->getCategory()?->getName(), (string) $right->getCategory()?->getName()),
                    'year' => ($left->getYear() ?? 0) <=> ($right->getYear() ?? 0),
                    'kind' => strcasecmp((string) $left->getKind()?->getName(), (string) $right->getKind()?->getName()),
                    'topic' => strcasecmp((string) $left->getTopic()?->getName(), (string) $right->getTopic()?->getName()),
                    'created_at' => $left->getCreatedAt() <=> $right->getCreatedAt(),
                    default => $left->getDocumentDate() <=> $right->getDocumentDate(),
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

        $strategyRoot = $categoryRepository->findStrategyRoot();
        $categoryRoots = $strategyRoot instanceof DocumentCategory ? [$strategyRoot] : [];

        return $this->render('document/index.html.twig', [
            'documents' => $documents,
            'documents_by_category' => $documentsByCategory,
            'category_roots' => $categoryRoots,
            'is_admin_list_view' => $isAdminListView,
            'can_upload' => $this->isGranted('ROLE_17B_ADMIN')
                || ($this->isGranted('ROLE_17B_USER') && $user->getManagedEntrepriseIds() !== []),
            'show_client_in_tree' => !$this->isGranted('ROLE_CUSTOMER'),
            'search_query' => $isAdminListView ? trim((string) $request->query->get('q', '')) : '',
            'filter_entreprise' => $isAdminListView ? (int) $request->query->get('entreprise', 0) : 0,
            'filter_category' => $isAdminListView ? (int) $request->query->get('category', 0) : 0,
            'sort_field' => $isAdminListView ? (string) $request->query->get('sort', 'document_date') : 'document_date',
            'sort_direction' => $isAdminListView && strtolower((string) $request->query->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc',
            'available_entreprises' => $availableEntreprises,
            'available_categories' => $availableCategories,
            'can_bulk_delete' => $user->is17bAdmin() && $isAdminListView,
            'section_title' => 'Stratégie',
            'index_route' => 'strategie_index',
            'upload_route' => 'strategie_batch_upload',
            'edit_route' => 'strategie_edit',
            'bulk_delete_route' => 'document_bulk_delete',
            'show_strategy_columns' => true,
        ]);
    }

    #[Route('/ajouter', name: 'strategie_batch_upload', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
    public function upload(
        Request $request,
        DocumentCategoryRepository $categoryRepository,
        DocumentKindRepository $kindRepository,
        DocumentTopicRepository $topicRepository,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
        DocumentStorage $storage,
        DocumentMimeResolver $mimeResolver,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $forcedEntreprise = null;
        if ($user->is17bStaff()) {
            $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
            if ($user->is17bUser() && !$forcedEntreprise instanceof Entreprise && $user->getManagedEntrepriseIds() === []) {
                $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à votre compte.');

                return $this->redirectToRoute('app_home');
            }
        }

        if ($request->isMethod('POST')) {
            $postMaxBytes = self::bytesFromIniSize((string) ini_get('post_max_size'));
            $contentLength = (int) $request->server->get('CONTENT_LENGTH', 0);
            if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
                $this->addFlash(
                    'error',
                    sprintf(
                        'Le total des fichiers envoyés dépasse la limite autorisée du serveur (%s). Réduisez la taille totale et réessayez.',
                        ini_get('post_max_size') ?: 'limite inconnue',
                    ),
                );

                return $this->redirectToRoute('strategie_batch_upload');
            }
        }

        $allowedEntreprises = $forcedEntreprise instanceof Entreprise
            ? [$forcedEntreprise]
            : $this->allowedClientEntreprises($user, $entrepriseRepository);
        $hasEntreprises = $allowedEntreprises !== [];
        $strategyChoices = $categoryRepository->buildStrategyCategoryChoices();
        if ($strategyChoices === []) {
            $this->addFlash('error', 'Les dossiers Stratégie / Pilotage ne sont pas configurés.');

            return $this->redirectToRoute('strategie_index');
        }

        $form = $this->createForm(DocumentBatchUploadType::class, options: [
            'category_choices' => $strategyChoices,
            'entreprise_choices' => $allowedEntreprises,
            'preselected_entreprise' => $forcedEntreprise,
            'lock_entreprise' => $forcedEntreprise instanceof Entreprise,
            'strategy_fields' => true,
            'kind_choices' => $kindRepository->findAllOrdered(),
            'topic_choices' => $topicRepository->findAllOrdered(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $titleBase = trim((string) $form->get('title')->getData());
            $category = $form->get('category')->getData();
            $year = $form->get('year')->getData();
            $kind = $form->get('kind')->getData();
            $topic = $form->get('topic')->getData();
            $externalUrlRaw = $form->get('externalUrl')->getData();
            $externalUrl = \is_string($externalUrlRaw) ? trim($externalUrlRaw) : '';
            /** @var Entreprise|null $selectedEntreprise */
            $selectedEntreprise = $forcedEntreprise instanceof Entreprise
                ? $forcedEntreprise
                : $form->get('entreprise')->getData();
            if (!$selectedEntreprise instanceof Entreprise || $selectedEntreprise->isAgency()) {
                $this->addFlash('error', 'Choisissez une entreprise cliente valide.');

                return $this->redirectToRoute('strategie_batch_upload');
            }
            if ($user->is17bUser() && !$user->managesEntreprise($selectedEntreprise)) {
                throw $this->createAccessDeniedException();
            }
            if (!$category instanceof DocumentCategory || !\in_array($category, $strategyChoices, true)) {
                $this->addFlash('error', 'Choisissez un dossier Stratégie ou Pilotage.');

                return $this->redirectToRoute('strategie_batch_upload');
            }
            if (!$kind instanceof DocumentKind || !$topic instanceof DocumentTopic || !\is_int($year)) {
                $this->addFlash('error', 'Année, type et sujet sont obligatoires.');

                return $this->redirectToRoute('strategie_batch_upload');
            }

            /** @var list<UploadedFile>|null $files */
            $files = $form->get('files')->getData();
            if (!\is_array($files)) {
                $files = [];
            }

            $validFiles = [];
            foreach ($files as $file) {
                if ($file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK) {
                    $validFiles[] = $file;
                }
            }

            $count = 0;
            if ($externalUrl !== '') {
                $doc = new Document();
                $doc->setEntreprise($selectedEntreprise);
                $doc->setClient(null);
                $doc->setUploadedBy($user);
                $doc->setTitle($titleBase);
                $doc->setCategory($category);
                $doc->setYear($year);
                $doc->setKind($kind);
                $doc->setTopic($topic);
                $doc->setOriginalName(null);
                $doc->setStorageName(null);
                $doc->setStoragePath(null);
                $doc->setMimeType(null);
                $doc->setSize(null);
                $doc->setExternalUrl($externalUrl);

                $entityManager->persist($doc);
                ++$count;
            } else {
                $multi = \count($validFiles) > 1;
                foreach ($validFiles as $file) {
                    $sizeBeforeMove = $file->getSize();
                    $mimeBeforeMove = $mimeResolver->resolveForUpload($file);
                    try {
                        $stored = $storage->storeUploadedFile($file, $user);
                    } catch (\Throwable) {
                        $this->addFlash(
                            'error',
                            sprintf(
                                'Impossible d’enregistrer « %s » : problème d’accès au stockage. Réessayez ou contactez un administrateur.',
                                $file->getClientOriginalName(),
                            ),
                        );

                        return $this->redirectToRoute('strategie_batch_upload');
                    }

                    $doc = new Document();
                    $doc->setEntreprise($selectedEntreprise);
                    $doc->setClient(null);
                    $doc->setUploadedBy($user);
                    $doc->setTitle($multi ? $titleBase.' — '.$file->getClientOriginalName() : $titleBase);
                    $doc->setCategory($category);
                    $doc->setYear($year);
                    $doc->setKind($kind);
                    $doc->setTopic($topic);
                    $doc->setOriginalName($file->getClientOriginalName());
                    $doc->setStorageName($stored['storageName']);
                    $doc->setStoragePath($stored['relativePath']);
                    $doc->setMimeType($mimeBeforeMove);
                    $doc->setSize((int) ($sizeBeforeMove ?: (is_file($stored['absolutePath']) ? filesize($stored['absolutePath']) : 0)));
                    $doc->setExternalUrl(null);

                    $entityManager->persist($doc);
                    ++$count;
                }
            }

            $entityManager->flush();

            if ($count === 0) {
                $this->addFlash('error', 'Aucun fichier valide n’a été reçu.');
            } else {
                $this->addFlash('success', sprintf('%d document(s) ajouté(s).', $count));
            }

            return $this->redirectToRoute('strategie_index');
        }

        return $this->render('document/batch.html.twig', [
            'form' => $form,
            'has_entreprises' => $hasEntreprises,
            'allowed_extensions_label' => DocumentUploadPolicy::extensionsLabel(),
            'section_title' => 'Stratégie',
            'index_route' => 'strategie_index',
            'show_strategy_fields' => true,
        ]);
    }

    #[Route('/{id}/edit', name: 'strategie_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Document $document,
        DocumentRepository $documentRepository,
        DocumentCategoryRepository $categoryRepository,
        DocumentKindRepository $kindRepository,
        DocumentTopicRepository $topicRepository,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyAccessUnlessGranted(DocumentVoter::MANAGE, $document);

        $user = $this->getUser();
        if (!$user instanceof User || !$user->is17bStaff()) {
            throw $this->createAccessDeniedException();
        }

        if (!$documentRepository->isStrategyDocument($document)) {
            return $this->redirectToRoute('document_edit', ['id' => $document->getId()]);
        }

        $forcedEntreprise = $managedClientContext->getSelectedManagedEntreprise($user);
        $allowedEntreprises = ($forcedEntreprise instanceof Entreprise && $user->is17bUser())
            ? [$forcedEntreprise]
            : $this->allowedClientEntreprises($user, $entrepriseRepository);
        if ($allowedEntreprises === []) {
            $this->addFlash('error', 'Aucune entreprise cliente disponible.');

            return $this->redirectToRoute('strategie_index');
        }

        $form = $this->createForm(DocumentEditType::class, $document, [
            'category_choices' => $categoryRepository->buildStrategyCategoryChoices(),
            'entreprise_choices' => $allowedEntreprises,
            'lock_entreprise' => $forcedEntreprise instanceof Entreprise && $user->is17bUser(),
            'strategy_fields' => true,
            'kind_choices' => $kindRepository->findAllOrdered(),
            'topic_choices' => $topicRepository->findAllOrdered(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entreprise = $document->getEntreprise();
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
                $this->addFlash('error', 'Choisis une entreprise cliente valide.');

                return $this->redirectToRoute('strategie_edit', ['id' => $document->getId()]);
            }

            if ($user->is17bUser() && !$user->managesEntreprise($entreprise)) {
                throw $this->createAccessDeniedException();
            }

            $entityManager->flush();
            $this->addFlash('success', 'Document mis à jour.');

            return $this->redirectToRoute('strategie_index');
        }

        return $this->render('document/form.html.twig', [
            'form' => $form,
            'document' => $document,
            'title' => 'Modifier le document stratégie',
            'index_route' => 'strategie_index',
            'section_title' => 'Stratégie',
            'show_strategy_fields' => true,
        ]);
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

    private static function bytesFromIniSize(string $value): int
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return 0;
        }

        $unit = strtolower($trimmed[\strlen($trimmed) - 1]);
        $bytes = (int) $trimmed;

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => (int) $trimmed,
        };
    }
}
