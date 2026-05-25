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
    public function index(Request $request, EntrepriseRepository $entrepriseRepository): Response
    {
        $entreprises = $entrepriseRepository->findAllOrdered();
        $search = trim((string) $request->query->get('q', ''));
        $typeFilter = (string) $request->query->get('type', 'all');
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'slug', 'type'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }
        if (!\in_array($typeFilter, ['all', 'agency', 'client'], true)) {
            $typeFilter = 'all';
        }

        $entreprises = array_values(array_filter($entreprises, static function (Entreprise $entreprise) use ($search, $typeFilter): bool {
            if ($typeFilter === 'agency' && !$entreprise->isAgency()) {
                return false;
            }
            if ($typeFilter === 'client' && $entreprise->isAgency()) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower($entreprise->getName().' '.($entreprise->getSlug() ?? ''));

            return str_contains($haystack, mb_strtolower($search));
        }));

        usort($entreprises, static function (Entreprise $left, Entreprise $right) use ($sort, $direction): int {
            $result = match ($sort) {
                'slug' => strcasecmp((string) $left->getSlug(), (string) $right->getSlug()),
                'type' => ($left->isAgency() <=> $right->isAgency()) * -1,
                default => strcasecmp($left->getName(), $right->getName()),
            };

            return $direction === 'asc' ? $result : -$result;
        });

        return $this->render('admin/entreprise/index.html.twig', [
            'entreprises' => $entreprises,
            'search_query' => $search,
            'filter_type' => $typeFilter,
            'sort_field' => $sort,
            'sort_direction' => $direction,
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
