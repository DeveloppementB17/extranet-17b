<?php

namespace App\Controller;

use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\DocumentCategoryType;
use App\Repository\DocumentCategoryRepository;
use App\Repository\DocumentRepository;
use App\Repository\EntrepriseRepository;
use App\Security\Voter\EntrepriseOwnedVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documents/categories')]
#[IsGranted(new Expression('is_granted("ROLE_17B_ADMIN") or is_granted("ROLE_17B_USER")'))]
final class DocumentCategoryController extends AbstractController
{
    #[Route('', name: 'document_category_index', methods: ['GET'])]
    public function index(DocumentCategoryRepository $categoryRepository): Response
    {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roots = $categoryRepository->findRootsFor17bStaff($actor);

        return $this->render('document_category/index.html.twig', [
            'rows' => $this->flattenCategories($roots),
        ]);
    }

    #[Route('/new', name: 'document_category_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        DocumentCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
    ): Response {
        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $allowedEntreprises = $this->allowedClientEntreprises($actor, $entrepriseRepository);
        if ($allowedEntreprises === []) {
            $this->addFlash('error', 'Aucune entreprise cliente n’est associée à ton compte.');

            return $this->redirectToRoute('document_category_index');
        }

        $category = new DocumentCategory();
        $form = $this->createForm(DocumentCategoryType::class, $category, [
            'entreprise_choices' => $allowedEntreprises,
            'parent_choices' => $this->buildParentChoicesForNew($allowedEntreprises, $categoryRepository),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $parent = $category->getParent();
            $entreprise = $category->getEntreprise();
            if ($parent !== null && $entreprise !== null
                && $parent->getEntreprise()->getId() !== $entreprise->getId()) {
                $this->addFlash('error', 'Le dossier parent doit appartenir à la même entreprise cliente.');

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
        $this->denyAccessUnlessGranted(EntrepriseOwnedVoter::EDIT, $category);

        $entreprise = $category->getEntreprise();
        $eid = $entreprise?->getId();
        $parentChoices = [];
        if ($eid !== null) {
            $exclude = $this->collectSubtreeIds($category);
            $parentChoices = array_values(array_filter(
                $categoryRepository->findAllInEntrepriseOrdered($eid),
                static fn (DocumentCategory $c): bool => !\in_array($c->getId(), $exclude, true),
            ));
        }

        $form = $this->createForm(DocumentCategoryType::class, $category, [
            'entreprise_choices' => [],
            'parent_choices' => $parentChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $parent = $category->getParent();
            if ($parent !== null && $entreprise !== null
                && $parent->getEntreprise()->getId() !== $entreprise->getId()) {
                $this->addFlash('error', 'Le dossier parent doit appartenir à la même entreprise cliente.');

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
        $this->denyAccessUnlessGranted(EntrepriseOwnedVoter::DELETE, $category);

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
     * @param list<Entreprise> $allowedEntreprises
     *
     * @return array<string, DocumentCategory>
     */
    private function buildParentChoicesForNew(array $allowedEntreprises, DocumentCategoryRepository $categoryRepository): array
    {
        $choices = [];
        foreach ($allowedEntreprises as $ent) {
            foreach ($categoryRepository->findRootsForEntreprise($ent) as $root) {
                $this->appendCategoryBranchChoices($root, $ent, '', $choices);
            }
        }

        return $choices;
    }

    /**
     * @param array<string, DocumentCategory> $choices
     */
    private function appendCategoryBranchChoices(
        DocumentCategory $category,
        Entreprise $entreprise,
        string $path,
        array &$choices,
    ): void {
        $label = $entreprise->getName().' — '.$path.$category->getName();
        $choices[$label] = $category;
        $next = $path === '' ? $category->getName().' / ' : $path.$category->getName().' / ';
        foreach ($category->getChildren() as $child) {
            $this->appendCategoryBranchChoices($child, $entreprise, $next, $choices);
        }
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
}
