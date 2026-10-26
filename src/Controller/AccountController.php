<?php

namespace App\Controller;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\User;
use App\Form\Account\AccountPreferredClientsType;
use App\Form\Account\TimeCreditInterventionBulkType;
use App\Form\AccountPasswordChangeType;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditRepository;
use App\Service\TimeCreditInterventionCsvImporter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    #[Route('/compte', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        EntrepriseRepository $entrepriseRepository,
        TimeCreditRepository $timeCreditRepository,
        TimeCreditInterventionCsvImporter $interventionImporter,
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $roles = $user->getRoles();
        usort($roles, static function (string $a, string $b): int {
            $la = preg_replace('/^ROLE_/', '', $a) ?: $a;
            $lb = preg_replace('/^ROLE_/', '', $b) ?: $b;

            return strcasecmp($la, $lb);
        });

        $passwordForm = $this->createForm(AccountPasswordChangeType::class);
        $preferredClientsForm = null;
        $bulkInterventionForm = null;
        $bulkInterventionResult = null;

        if ($user->is17bAdmin()) {
            $preferredClientsForm = $this->createForm(AccountPreferredClientsType::class, $user, [
                'client_entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(),
            ]);
            $bulkInterventionForm = $this->createForm(TimeCreditInterventionBulkType::class, [
                'rows' => [
                    [
                        'entreprise' => null,
                        'timeCredit' => null,
                        'date' => new \DateTimeImmutable('today'),
                        'duree' => '',
                        'descriptif' => '',
                    ],
                ],
            ], [
                'entreprise_choices' => $entrepriseRepository->findNonAgencyOrdered(includeLegacy: true),
                'time_credit_choices' => $timeCreditRepository->findNonArchivedWithEntrepriseOrdered(),
            ]);
        }

        $submittedForm = (string) $request->request->get('_account_form', '');

        if ($submittedForm === 'password') {
            $passwordForm->handleRequest($request);

            if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
                $currentPassword = (string) $passwordForm->get('currentPassword')->getData();
                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $passwordForm->get('currentPassword')->addError(
                        new FormError('Le mot de passe actuel est incorrect.'),
                    );
                } else {
                    $newPassword = (string) $passwordForm->get('plainPassword')->getData();
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    $user->clearPasswordReset();
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

                    return $this->redirectToRoute('app_account');
                }
            }
        }

        if ($submittedForm === 'preferred_clients' && $preferredClientsForm !== null) {
            $preferredClientsForm->handleRequest($request);

            if ($preferredClientsForm->isSubmitted() && $preferredClientsForm->isValid()) {
                foreach ($user->getManagedEntreprises()->toArray() as $entreprise) {
                    if ($entreprise->isAgency()) {
                        $user->removeManagedEntreprise($entreprise);
                    }
                }
                $entityManager->flush();

                $this->addFlash('success', 'Vos clients rattachés ont été mis à jour.');

                return $this->redirectToRoute('app_account');
            }
        }

        if ($submittedForm === 'bulk_interventions' && $bulkInterventionForm !== null) {
            $bulkInterventionForm->handleRequest($request);

            if ($bulkInterventionForm->isSubmitted() && $bulkInterventionForm->isValid()) {
                $allowOverdraft = (bool) $bulkInterventionForm->get('allowOverdraft')->getData();
                /** @var list<array{entreprise?: ?Entreprise, timeCredit?: ?TimeCredit, duree?: string, date?: \DateTimeImmutable|null, descriptif?: string}> $formRows */
                $formRows = $bulkInterventionForm->get('rows')->getData() ?? [];

                $rows = [];
                foreach ($formRows as $index => $formRow) {
                    $date = $formRow['date'] ?? null;
                    $rows[] = [
                        'line' => $index + 1,
                        'entreprise' => $formRow['entreprise'] ?? null,
                        'timeCredit' => $formRow['timeCredit'] ?? null,
                        'duree' => (string) ($formRow['duree'] ?? ''),
                        'date' => $date instanceof \DateTimeInterface ? $date : '',
                        'descriptif' => (string) ($formRow['descriptif'] ?? ''),
                    ];
                }

                $bulkInterventionResult = $interventionImporter->importCreditRows(
                    $rows,
                    $user,
                    dryRun: false,
                    allowOverdraft: $allowOverdraft,
                );

                if ($bulkInterventionResult['errors'] === [] && $bulkInterventionResult['imported'] > 0) {
                    $this->addFlash(
                        'success',
                        sprintf('%d intervention(s) enregistrée(s).', $bulkInterventionResult['imported']),
                    );

                    return $this->redirectToRoute('app_account', ['_fragment' => 'interventions-bulk']);
                }

                if ($bulkInterventionResult['imported'] > 0) {
                    $this->addFlash(
                        'success',
                        sprintf(
                            '%d enregistrée(s), %d ignorée(s).',
                            $bulkInterventionResult['imported'],
                            $bulkInterventionResult['skipped'],
                        ),
                    );
                } elseif ($bulkInterventionResult['errors'] !== []) {
                    $this->addFlash('error', 'Aucune intervention enregistrée. Voir le détail ci-dessous.');
                }
            }
        }

        return $this->render('account/index.html.twig', [
            'user' => $user,
            'display_roles' => $roles,
            'password_form' => $passwordForm,
            'preferred_clients_form' => $preferredClientsForm,
            'bulk_intervention_form' => $bulkInterventionForm,
            'bulk_intervention_result' => $bulkInterventionResult,
        ]);
    }
}
