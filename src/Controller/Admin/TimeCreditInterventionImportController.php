<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Form\Admin\TimeCreditInterventionCsvImportType;
use App\Repository\EntrepriseRepository;
use App\Service\TimeCreditInterventionCsvImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/credits/interventions-csv')]
#[IsGranted('ROLE_17B_ADMIN')]
final class TimeCreditInterventionImportController extends AbstractController
{
    #[Route('', name: 'admin_time_credit_intervention_csv_import', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        EntrepriseRepository $entrepriseRepository,
        TimeCreditInterventionCsvImporter $importer,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(TimeCreditInterventionCsvImportType::class, null, [
            'entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(includeLegacy: true),
        ]);
        $form->handleRequest($request);

        $result = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Entreprise $entreprise */
            $entreprise = $form->get('entreprise')->getData();
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            $dryRun = (bool) $form->get('dryRun')->getData();
            $allowOverdraft = (bool) $form->get('allowOverdraft')->getData();

            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Fichier CSV manquant.');
            } else {
                $content = (string) file_get_contents($file->getPathname());
                $result = $importer->import(
                    $entreprise,
                    $content,
                    $user,
                    dryRun: $dryRun,
                    allowOverdraft: $allowOverdraft,
                );

                if ($result['errors'] === [] && $result['imported'] > 0) {
                    $this->addFlash(
                        'success',
                        $dryRun
                            ? sprintf('Simulation OK : %d intervention(s) prête(s) à importer.', $result['imported'])
                            : sprintf('%d intervention(s) importée(s).', $result['imported']),
                    );
                } elseif ($result['imported'] > 0) {
                    $this->addFlash(
                        'success',
                        $dryRun
                            ? sprintf('Simulation partielle : %d OK, %d ignorée(s).', $result['imported'], $result['skipped'])
                            : sprintf('%d importée(s), %d ignorée(s).', $result['imported'], $result['skipped']),
                    );
                } elseif ($result['errors'] !== []) {
                    $this->addFlash('error', 'Aucune intervention importée. Voir le détail ci-dessous.');
                }
            }
        }

        return $this->render('admin/time_credit_intervention_csv_import.html.twig', [
            'form' => $form,
            'result' => $result,
        ]);
    }

    #[Route('/modele.csv', name: 'admin_time_credit_intervention_csv_sample', methods: ['GET'])]
    public function sample(): Response
    {
        $csv = "dossier;duree;date;descriptif\n"
            ."D-2024-001;90;15/01/2026;Correction bug formulaire contact\n"
            ."D-2024-001;1h30;20/01/2026;Mise à jour page Accueil\n"
            ."D-2024-002;45;22/01/2026;Assistance technique\n";

        return new Response($csv, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-interventions.csv"',
        ]);
    }
}
