<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entreprise\LegacyEntrepriseExchange;
use App\Form\Admin\LegacyEntrepriseImportType;
use App\Repository\EntrepriseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/import-export')]
#[IsGranted('ROLE_17B_ADMIN')]
final class AdminImportExportController extends AbstractController
{
    #[Route('', name: 'admin_import_export', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        LegacyEntrepriseExchange $exchange,
        EntrepriseRepository $entrepriseRepository,
    ): Response {
        $form = $this->createForm(LegacyEntrepriseImportType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile|null $file */
            $file = $form->get('file')->getData();
            $includeArchived = (bool) $form->get('includeArchived')->getData();

            if (!$file instanceof UploadedFile) {
                $this->addFlash('error', 'Fichier manquant.');

                return $this->redirectToRoute('admin_import_export');
            }

            try {
                $payload = $exchange->decodeJson((string) file_get_contents($file->getPathname()));
                $result = $exchange->importPayload($payload, includeArchived: $includeArchived);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Import impossible : '.$e->getMessage());

                return $this->redirectToRoute('admin_import_export');
            }

            $this->addFlash(
                'success',
                sprintf(
                    'Import terminé : %d créée(s), %d mise(s) à jour, %d ignorée(s).',
                    $result->created,
                    $result->updated,
                    $result->skipped,
                ),
            );

            if ($result->skippedReasons !== []) {
                $this->addFlash(
                    'error',
                    'Détail (extrait) : '.implode(' · ', \array_slice($result->skippedReasons, 0, 8)),
                );
            }

            return $this->redirectToRoute('admin_import_export');
        }

        return $this->render('admin/import_export/index.html.twig', [
            'form' => $form,
            'legacy_count' => $entrepriseRepository->countLegacy(),
        ]);
    }

    #[Route('/export-entreprises-legacy.json', name: 'admin_import_export_legacy_entreprises', methods: ['GET'])]
    public function exportLegacyEntreprises(LegacyEntrepriseExchange $exchange): Response
    {
        $payload = $exchange->exportFromDatabase();
        $json = $exchange->encodeJson($payload);
        $filename = sprintf('legacy-entreprises-%s.json', (new \DateTimeImmutable())->format('Ymd-His'));

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
