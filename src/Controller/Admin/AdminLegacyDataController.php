<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditCategory;
use App\Entity\User;
use App\Entreprise\LegacyCatalogStorage;
use App\Entreprise\LegacyEntrepriseExchange;
use App\Entreprise\LegacySelectiveImporter;
use App\Entreprise\LegacyTimeCreditExchange;
use App\Form\Admin\LegacyCatalogUploadType;
use App\Form\Admin\LegacyTimeCreditImportFormType;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditCategoryRepository;
use App\Repository\TimeCreditRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/anciennes-donnees')]
#[IsGranted('ROLE_17B_ADMIN')]
final class AdminLegacyDataController extends AbstractController
{
    #[Route('', name: 'admin_legacy_data', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        LegacyCatalogStorage $catalogStorage,
        LegacyEntrepriseExchange $entrepriseExchange,
        LegacyTimeCreditExchange $timeCreditExchange,
    ): Response {
        $form = $this->createForm(LegacyCatalogUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $entreprisesFile */
            $entreprisesFile = $form->get('entreprisesFile')->getData();
            /** @var UploadedFile|null $creditsFile */
            $creditsFile = $form->get('timeCreditsFile')->getData();

            if (!$entreprisesFile instanceof UploadedFile && !$creditsFile instanceof UploadedFile) {
                $this->addFlash('error', 'Sélectionnez au moins un fichier JSON.');

                return $this->redirectToRoute('admin_legacy_data');
            }

            try {
                if ($entreprisesFile instanceof UploadedFile) {
                    $json = (string) file_get_contents($entreprisesFile->getPathname());
                    $entrepriseExchange->decodeJson($json);
                    $catalogStorage->storeEntreprisesJson($json);
                }
                if ($creditsFile instanceof UploadedFile) {
                    $json = (string) file_get_contents($creditsFile->getPathname());
                    $timeCreditExchange->decodeJson($json);
                    $catalogStorage->storeTimeCreditsJson($json);
                }
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Chargement impossible : '.$e->getMessage());

                return $this->redirectToRoute('admin_legacy_data');
            }

            $this->addFlash('success', 'Catalogue(s) chargé(s).');

            return $this->redirectToRoute('admin_legacy_data');
        }

        return $this->render('admin/legacy_data/index.html.twig', [
            'form' => $form,
            'has_entreprises' => $catalogStorage->hasEntreprisesCatalog(),
            'has_time_credits' => $catalogStorage->hasTimeCreditsCatalog(),
        ]);
    }

    #[Route('/entreprises', name: 'admin_legacy_data_entreprises', methods: ['GET'])]
    public function entreprises(Request $request, LegacySelectiveImporter $importer, LegacyCatalogStorage $catalogStorage): Response
    {
        if (!$catalogStorage->hasEntreprisesCatalog()) {
            $this->addFlash('error', 'Chargez d’abord le catalogue entreprises.');

            return $this->redirectToRoute('admin_legacy_data');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'name');
        $direction = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'source', 'status'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'name';
        }

        $rows = $importer->listEntreprises($search !== '' ? $search : null);
        $statusOrder = ['available' => 0, 'name_conflict' => 1, 'imported' => 2];
        usort($rows, static function (array $left, array $right) use ($sort, $direction, $statusOrder): int {
            $result = match ($sort) {
                'source' => $left['legacySourceId'] <=> $right['legacySourceId'],
                'status' => ($statusOrder[$left['status']] ?? 99) <=> ($statusOrder[$right['status']] ?? 99)
                    ?: strcasecmp($left['name'], $right['name']),
                default => strcasecmp($left['name'], $right['name']),
            };

            return $direction === 'asc' ? $result : -$result;
        });

        return $this->render('admin/legacy_data/entreprises.html.twig', [
            'rows' => $rows,
            'search_query' => $search,
            'sort_field' => $sort,
            'sort_direction' => $direction,
        ]);
    }

    #[Route('/entreprises/{legacySourceId}/importer', name: 'admin_legacy_data_entreprise_import', methods: ['POST'], requirements: ['legacySourceId' => '\d+'])]
    public function importEntreprise(
        int $legacySourceId,
        Request $request,
        LegacySelectiveImporter $importer,
    ): Response {
        if (!$this->isCsrfTokenValid('legacy_import_entreprise'.$legacySourceId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $overwrite = $request->request->getBoolean('overwrite');

        try {
            $entreprise = $importer->importEntreprise($legacySourceId, overwriteNameConflict: $overwrite);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_legacy_data_entreprises', $this->listQuery($request));
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Import impossible : '.$e->getMessage());

            return $this->redirectToRoute('admin_legacy_data_entreprises', $this->listQuery($request));
        }

        $this->addFlash('success', sprintf('Entreprise « %s » importée / liée.', $entreprise->getName()));

        return $this->redirectToRoute('admin_legacy_data_entreprises', $this->listQuery($request));
    }

    #[Route('/credits-temps', name: 'admin_legacy_data_time_credits', methods: ['GET'])]
    public function timeCredits(Request $request, LegacySelectiveImporter $importer, LegacyCatalogStorage $catalogStorage): Response
    {
        if (!$catalogStorage->hasTimeCreditsCatalog()) {
            $this->addFlash('error', 'Chargez d’abord le catalogue crédits temps.');

            return $this->redirectToRoute('admin_legacy_data');
        }

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'entreprise');
        $direction = strtolower((string) $request->query->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['entreprise', 'date', 'total', 'used', 'remaining', 'interventions', 'status'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'entreprise';
        }

        $rows = $importer->listTimeCredits($search !== '' ? $search : null);
        $statusOrder = ['available' => 0, 'entreprise_missing' => 1, 'imported' => 2];
        usort($rows, static function (array $left, array $right) use ($sort, $direction, $statusOrder): int {
            $result = match ($sort) {
                'date' => strcmp($left['creditedAt'], $right['creditedAt']),
                'total' => $left['totalMinutes'] <=> $right['totalMinutes'],
                'used' => $left['usedMinutes'] <=> $right['usedMinutes'],
                'remaining' => $left['remainingMinutes'] <=> $right['remainingMinutes'],
                'interventions' => $left['interventionCount'] <=> $right['interventionCount'],
                'status' => ($statusOrder[$left['status']] ?? 99) <=> ($statusOrder[$right['status']] ?? 99)
                    ?: strcasecmp($left['entrepriseName'], $right['entrepriseName']),
                default => strcasecmp($left['entrepriseName'], $right['entrepriseName']),
            };

            return $direction === 'asc' ? $result : -$result;
        });

        return $this->render('admin/legacy_data/time_credits.html.twig', [
            'rows' => $rows,
            'search_query' => $search,
            'sort_field' => $sort,
            'sort_direction' => $direction,
        ]);
    }

    #[Route('/credits-temps/{legacySourceId}', name: 'admin_legacy_data_time_credit_show', methods: ['GET', 'POST'], requirements: ['legacySourceId' => '\d+'])]
    public function timeCreditShow(
        int $legacySourceId,
        Request $request,
        LegacySelectiveImporter $importer,
        LegacyCatalogStorage $catalogStorage,
        TimeCreditCategoryRepository $categoryRepository,
        EntrepriseRepository $entrepriseRepository,
        TimeCreditRepository $timeCreditRepository,
    ): Response {
        if (!$catalogStorage->hasTimeCreditsCatalog()) {
            $this->addFlash('error', 'Chargez d’abord le catalogue crédits temps.');

            return $this->redirectToRoute('admin_legacy_data');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $item = $importer->getTimeCreditItem($legacySourceId);
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('admin_legacy_data_time_credits');
        }

        $entrepriseName = sprintf('Entreprise #%d', $item['entrepriseLegacySourceId']);
        if ($catalogStorage->hasEntreprisesCatalog()) {
            try {
                $entrepriseName = $importer->getEntrepriseItem($item['entrepriseLegacySourceId'])['name'];
            } catch (\Throwable) {
            }
        }

        $matchedEntreprise = $entrepriseRepository->findOneByLegacySourceId($item['entrepriseLegacySourceId']);
        $requireEntrepriseChoice = !$matchedEntreprise instanceof Entreprise;
        $existingCredit = $timeCreditRepository->findOneByLegacySourceId($legacySourceId);

        $used = 0;
        foreach ($item['interventions'] as $intervention) {
            $used += (int) $intervention['minutes'];
        }

        $defaultTitle = sprintf(
            'Crédit importé du %s — %s',
            (new \DateTimeImmutable($item['creditedAt']))->format('d/m/Y'),
            $entrepriseName,
        );

        $form = $this->createForm(LegacyTimeCreditImportFormType::class, [
            'title' => $defaultTitle,
            'category' => null,
            'dossierNumber' => null,
            'siteUrl' => null,
            'entreprise' => $matchedEntreprise,
        ], [
            'category_choices' => $categoryRepository->findAllOrdered(),
            'entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(),
            'require_entreprise_choice' => $requireEntrepriseChoice,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && !$existingCredit instanceof TimeCredit) {
            /** @var TimeCreditCategory|null $category */
            $category = $form->get('category')->getData();
            /** @var Entreprise|null $override */
            $override = $requireEntrepriseChoice ? $form->get('entreprise')->getData() : null;

            try {
                $credit = $importer->importTimeCredit(
                    $legacySourceId,
                    $user,
                    (string) $form->get('title')->getData(),
                    $category instanceof TimeCreditCategory ? $category : null,
                    $form->get('dossierNumber')->getData(),
                    $form->get('siteUrl')->getData(),
                    $override instanceof Entreprise ? $override : null,
                );
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Import impossible : '.$e->getMessage());

                return $this->redirectToRoute('admin_legacy_data_time_credit_show', ['legacySourceId' => $legacySourceId]);
            }

            $this->addFlash('success', sprintf('Crédit « %s » importé.', $credit->getTitle()));

            return $this->redirectToRoute('time_credit_show', ['id' => $credit->getId()]);
        }

        return $this->render('admin/legacy_data/time_credit_show.html.twig', [
            'item' => $item,
            'entreprise_name' => $entrepriseName,
            'matched_entreprise' => $matchedEntreprise,
            'existing_credit' => $existingCredit,
            'used_minutes' => $used,
            'remaining_minutes' => max(0, (int) $item['totalMinutes'] - $used),
            'form' => $form,
            'require_entreprise_choice' => $requireEntrepriseChoice,
        ]);
    }

    /**
     * @return array<string, scalar>
     */
    private function listQuery(Request $request): array
    {
        $params = [];

        $q = trim((string) $request->query->get('q', ''));
        if ($q === '') {
            $q = trim((string) $request->request->get('q', ''));
        }
        if ($q !== '') {
            $params['q'] = $q;
        }

        $sort = (string) $request->query->get('sort', '');
        if ($sort === '') {
            $sort = (string) $request->request->get('sort', '');
        }
        if ($sort !== '') {
            $params['sort'] = $sort;
        }

        $dir = strtolower((string) $request->query->get('dir', ''));
        if ($dir === '') {
            $dir = strtolower((string) $request->request->get('dir', ''));
        }
        if (\in_array($dir, ['asc', 'desc'], true)) {
            $params['dir'] = $dir;
        }

        return $params;
    }
}
