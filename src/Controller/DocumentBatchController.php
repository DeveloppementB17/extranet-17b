<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\DocumentBatchUploadType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\EntrepriseRepository;
use App\Tenant\ManagedClientContext;
use App\Storage\DocumentStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/ajouter')]
#[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
final class DocumentBatchController extends AbstractController
{
    #[Route('', name: 'document_batch_upload', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        DocumentCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
        ManagedClientContext $managedClientContext,
        DocumentStorage $storage,
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
                $this->addFlash('error', 'Aucune entreprise cliente n’est attribuée à ton compte.');

                return $this->redirectToRoute('app_home');
            }
        }

        $allowedEntreprises = $forcedEntreprise instanceof Entreprise
            ? [$forcedEntreprise]
            : $this->allowedClientEntreprises($user, $entrepriseRepository);
        $hasEntreprises = $allowedEntreprises !== [];

        $form = $this->createForm(DocumentBatchUploadType::class, options: [
            'category_choices' => $this->buildCategoryChoices($categoryRepository),
            'entreprise_choices' => $allowedEntreprises,
            'preselected_entreprise' => $forcedEntreprise,
            'lock_entreprise' => $forcedEntreprise instanceof Entreprise,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $titleBase = trim((string) $form->get('title')->getData());
            $category = $form->get('category')->getData();
            $externalUrlRaw = $form->get('externalUrl')->getData();
            $externalUrl = \is_string($externalUrlRaw) ? trim($externalUrlRaw) : '';
            /** @var Entreprise|null $selectedEntreprise */
            $selectedEntreprise = $forcedEntreprise instanceof Entreprise
                ? $forcedEntreprise
                : $form->get('entreprise')->getData();
            if (!$selectedEntreprise instanceof Entreprise || $selectedEntreprise->isAgency()) {
                $this->addFlash('error', 'Choisis une entreprise cliente valide.');

                return $this->redirectToRoute('document_batch_upload');
            }
            if ($user->is17bUser() && !$user->managesEntreprise($selectedEntreprise)) {
                throw $this->createAccessDeniedException();
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
                if ($category instanceof DocumentCategory) {
                    $doc->setCategory($category);
                }
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
                    $mimeBeforeMove = (string) ($file->getClientMimeType() ?: 'application/octet-stream');
                    $stored = $storage->storeUploadedFile($file, $user);

                    $doc = new Document();
                    $doc->setEntreprise($selectedEntreprise);
                    $doc->setClient(null);
                    $doc->setUploadedBy($user);
                    $doc->setTitle($multi ? $titleBase.' — '.$file->getClientOriginalName() : $titleBase);
                    if ($category instanceof DocumentCategory) {
                        $doc->setCategory($category);
                    }
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

            return $this->redirectToRoute('document_index');
        }

        return $this->render('document/batch.html.twig', [
            'form' => $form,
            'has_entreprises' => $hasEntreprises,
        ]);
    }

    /**
     * @return array<string, DocumentCategory>
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

    /**
     * @param array<string, DocumentCategory> $choices
     */
    private function appendCategoryBranchChoices(
        DocumentCategory $category,
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
}
