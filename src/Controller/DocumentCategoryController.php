<?php

namespace App\Controller;

use App\Entity\DocumentCategory;
use App\Form\DocumentCategoryType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/categories')]
#[IsGranted('ROLE_17B_ADMIN')]
final class DocumentCategoryController extends AbstractController
{
    #[Route('', name: 'document_category_index', methods: ['GET'])]
    public function index(
        DocumentCategoryRepository $categoryRepository,
        DocumentRepository $documentRepository,
    ): Response
    {
        $roots = $categoryRepository->findRoots();
        $rows = $this->flattenCategories($roots);
        $categoryIds = [];
        foreach ($rows as $row) {
            $id = $row['category']->getId();
            if ($id !== null) {
                $categoryIds[] = $id;
            }
        }
        $categoryDocumentCounts = $documentRepository->countByCategoryIds($categoryIds);

        return $this->render('document_category/index.html.twig', [
            'rows' => $rows,
            'category_document_counts' => $categoryDocumentCounts,
        ]);
    }

    #[Route('/new', name: 'document_category_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentCategoryRepository $categoryRepository,
    ): Response {
        $category = new DocumentCategory();
        $form = $this->createForm(DocumentCategoryType::class, $category, [
            'parent_choices' => $categoryRepository->findAllOrdered(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->categoryNameExists($categoryRepository, $category->getName())) {
                $this->addFlash('error', 'Un dossier avec ce nom existe déjà.');

                return $this->render('document_category/form.html.twig', [
                    'form' => $form,
                    'title' => 'Nouveau dossier',
                ]);
            }

            $entityManager->persist($category);
            $entityManager->flush();
            $this->addFlash('success', 'Dossier créé.');

            return $this->redirectToRoute('document_category_index');
        }

        return $this->render('document_category/form.html.twig', [
            'form' => $form,
            'title' => 'Nouveau dossier',
        ]);
    }

    #[Route('/{id}/edit', name: 'document_category_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        DocumentCategory $category,
        EntityManagerInterface $entityManager,
        DocumentCategoryRepository $categoryRepository,
    ): Response {
        $exclude = $this->collectSubtreeIds($category);
        $parentChoices = array_values(array_filter(
            $categoryRepository->findAllOrdered(),
            static fn (DocumentCategory $c): bool => !\in_array($c->getId(), $exclude, true),
        ));

        $form = $this->createForm(DocumentCategoryType::class, $category, [
            'parent_choices' => $parentChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->categoryNameExists($categoryRepository, $category->getName(), $category->getId())) {
                $this->addFlash('error', 'Un dossier avec ce nom existe déjà.');

                return $this->render('document_category/form.html.twig', [
                    'form' => $form,
                    'title' => 'Modifier le dossier',
                ]);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Dossier mis à jour.');

            return $this->redirectToRoute('document_category_index');
        }

        return $this->render('document_category/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le dossier',
        ]);
    }

    #[Route('/{id}/delete', name: 'document_category_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        DocumentCategory $category,
        EntityManagerInterface $entityManager,
        DocumentRepository $documentRepository,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_category'.$category->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        if (!$category->getChildren()->isEmpty()) {
            $this->addFlash('error', 'Impossible de supprimer un dossier qui contient des sous-dossiers.');

            return $this->redirectToRoute('document_category_index');
        }

        if ($documentRepository->countByCategory($category) > 0) {
            $this->addFlash('error', 'Impossible de supprimer un dossier encore utilisé par des documents.');

            return $this->redirectToRoute('document_category_index');
        }

        $entityManager->remove($category);
        $entityManager->flush();
        $this->addFlash('success', 'Dossier supprimé.');

        return $this->redirectToRoute('document_category_index');
    }

    /**
     * @param list<DocumentCategory> $roots
     *
     * @return list<array{category: DocumentCategory, depth: int}>
     */
    private function flattenCategories(array $roots, int $depth = 0): array
    {
        $rows = [];
        foreach ($roots as $root) {
            $rows[] = ['category' => $root, 'depth' => $depth];
            $children = $root->getChildren()->toArray();
            usort($children, static fn (DocumentCategory $a, DocumentCategory $b): int => strcasecmp($a->getName(), $b->getName()));
            $rows = array_merge($rows, $this->flattenCategories($children, $depth + 1));
        }

        return $rows;
    }

    /**
     * @return list<int|null>
     */
    private function collectSubtreeIds(DocumentCategory $root): array
    {
        $ids = [$root->getId()];
        foreach ($root->getChildren() as $child) {
            $ids = array_merge($ids, $this->collectSubtreeIds($child));
        }

        return $ids;
    }

    private function categoryNameExists(
        DocumentCategoryRepository $categoryRepository,
        string $name,
        ?int $excludeId = null,
    ): bool {
        $normalizedName = mb_strtolower(trim($name));
        if ($normalizedName === '') {
            return false;
        }

        foreach ($categoryRepository->findAllOrdered() as $existing) {
            $existingId = $existing->getId();
            if ($excludeId !== null && $existingId === $excludeId) {
                continue;
            }

            if (mb_strtolower(trim($existing->getName())) === $normalizedName) {
                return true;
            }
        }

        return false;
    }
}
