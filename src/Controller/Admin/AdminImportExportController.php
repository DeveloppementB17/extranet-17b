<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entreprise\LegacyEntrepriseExchange;
use App\Entreprise\LegacyTimeCreditExchange;
use App\Form\Admin\LegacyEntrepriseImportType;
use App\Form\Admin\LegacyTimeCreditImportType;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditRepository;
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
    public function __invoke(): Response
    {
        return $this->redirectToRoute('admin_legacy_data');
    }

    #[Route('/entreprises', name: 'admin_import_export_entreprises', methods: ['POST'])]
    public function importEntreprises(
        Request $request,
        LegacyEntrepriseExchange $exchange,
    ): Response {
        $form = $this->createForm(LegacyEntrepriseImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Formulaire entreprises invalide.');

            return $this->redirectToRoute('admin_import_export');
        }

        /** @var UploadedFile|null $file */
        $file = $form->get('file')->getData();
        $includeArchived = (bool) $form->get('includeArchived')->getData();
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Fichier entreprises manquant.');

            return $this->redirectToRoute('admin_import_export');
        }

        try {
            $payload = $exchange->decodeJson((string) file_get_contents($file->getPathname()));
            $result = $exchange->importPayload($payload, includeArchived: $includeArchived);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Import entreprises impossible : '.$e->getMessage());

            return $this->redirectToRoute('admin_import_export');
        }

        $this->addFlash(
            'success',
            sprintf(
                'Entreprises : %d créée(s), %d mise(s) à jour, %d ignorée(s).',
                $result->created,
                $result->updated,
                $result->skipped,
            ),
        );
        if ($result->skippedReasons !== []) {
            $this->addFlash('error', 'Détail (extrait) : '.implode(' · ', \array_slice($result->skippedReasons, 0, 8)));
        }

        return $this->redirectToRoute('admin_import_export');
    }

    #[Route('/credits-temps', name: 'admin_import_export_time_credits', methods: ['POST'])]
    public function importTimeCredits(
        Request $request,
        LegacyTimeCreditExchange $exchange,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(LegacyTimeCreditImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Formulaire crédits temps invalide.');

            return $this->redirectToRoute('admin_import_export');
        }

        /** @var UploadedFile|null $file */
        $file = $form->get('file')->getData();
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Fichier crédits temps manquant.');

            return $this->redirectToRoute('admin_import_export');
        }

        try {
            $payload = $exchange->decodeJson((string) file_get_contents($file->getPathname()));
            $result = $exchange->importPayload($payload, createdBy: $user);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Import crédits temps impossible : '.$e->getMessage());

            return $this->redirectToRoute('admin_import_export');
        }

        $this->addFlash(
            'success',
            sprintf(
                'Crédits temps : %d créé(s), %d ignoré(s).',
                $result->created,
                $result->skipped,
            ),
        );
        if ($result->skippedReasons !== []) {
            $this->addFlash('error', 'Détail (extrait) : '.implode(' · ', \array_slice($result->skippedReasons, 0, 8)));
        }

        return $this->redirectToRoute('admin_import_export');
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

    #[Route('/export-credits-temps-legacy.json', name: 'admin_import_export_legacy_time_credits', methods: ['GET'])]
    public function exportLegacyTimeCredits(LegacyTimeCreditExchange $exchange): Response
    {
        $payload = $exchange->exportFromDatabase();
        $json = $exchange->encodeJson($payload);
        $filename = sprintf('legacy-time-credits-%s.json', (new \DateTimeImmutable())->format('Ymd-His'));

        return new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
