<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Anciennes URLs CSV → redirection vers le tableau d’interventions sur Mon compte.
 */
#[IsGranted('ROLE_17B_ADMIN')]
final class TimeCreditInterventionImportController extends AbstractController
{
    #[Route('/compte/interventions-csv', name: 'account_time_credit_intervention_csv_import', methods: ['GET', 'POST'])]
    #[Route('/compte/interventions-csv/modele.csv', name: 'account_time_credit_intervention_csv_sample', methods: ['GET'])]
    #[Route('/admin/credits/interventions-csv', name: 'admin_time_credit_intervention_csv_import_legacy', methods: ['GET', 'POST'])]
    #[Route('/admin/credits/interventions-csv/modele.csv', name: 'admin_time_credit_intervention_csv_sample_legacy', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->redirectToRoute('app_account', ['_fragment' => 'interventions-bulk'], Response::HTTP_MOVED_PERMANENTLY);
    }
}
