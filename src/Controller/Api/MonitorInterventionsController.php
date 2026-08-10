<?php

namespace App\Controller\Api;

use App\Entity\TimeCreditMovement;
use App\Entity\User;
use App\Repository\TimeCreditMovementRepository;
use App\Service\Monitor\SiteUrlMatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/monitor')]
final class MonitorInterventionsController extends AbstractController
{
    public function __construct(
        private readonly TimeCreditMovementRepository $movementRepository,
        private readonly SiteUrlMatcher $siteUrlMatcher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/interventions', name: 'api_monitor_interventions', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $siteUrl = $this->siteUrlMatcher->normalize($request->query->getString('site_url'));
        $timeCreditId = $this->positiveInt($request->query->get('time_credit_id'));
        $entrepriseId = $this->positiveInt($request->query->get('entreprise_id'));
        $limit = min(50, max(1, (int) $request->query->get('limit', 15)));

        if ($siteUrl === null && $timeCreditId === null && $entrepriseId === null) {
            return $this->json([
                'error' => 'invalid_query',
                'message' => 'Indiquez site_url, time_credit_id ou entreprise_id.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $filters = $this->entityManager->getFilters();
        $wasEnabled = $filters->isEnabled('entreprise');
        if ($wasEnabled) {
            $filters->disable('entreprise');
        }

        try {
            $rows = $this->movementRepository->findInterventionsForMonitor(
                $this->siteUrlMatcher,
                $timeCreditId,
                $entrepriseId,
                $siteUrl,
                $limit,
            );
        } finally {
            if ($wasEnabled) {
                $filters->enable('entreprise');
            }
        }

        return $this->json([
            'interventions' => array_map(
                fn (array $row): array => $this->serializeMovement($row['movement'], $row['match_reason']),
                $rows,
            ),
            'meta' => [
                'count' => count($rows),
                'site_url' => $siteUrl,
                'time_credit_id' => $timeCreditId,
                'entreprise_id' => $entrepriseId,
            ],
        ]);
    }

    private function serializeMovement(TimeCreditMovement $movement, string $matchReason): array
    {
        $credit = $movement->getTimeCredit();
        $entreprise = $credit?->getEntreprise();
        $author = $movement->getCreatedBy();

        return [
            'id' => $movement->getId(),
            'type' => $movement->getType(),
            'occurred_at' => $movement->getOccurredAt()->format(\DateTimeInterface::ATOM),
            'created_at' => $movement->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'duration_minutes' => abs($movement->getDeltaMinutes()),
            'delta_minutes' => $movement->getDeltaMinutes(),
            'description' => $movement->getDescription(),
            'created_by' => $this->formatAuthor($author),
            'match_reason' => $matchReason,
            'time_credit' => $credit === null ? null : [
                'id' => $credit->getId(),
                'title' => $credit->getTitle(),
                'site_url' => $credit->getSiteUrl(),
                'dossier_number' => $credit->getDossierNumber(),
                'category' => $credit->getCategory()?->getName(),
            ],
            'entreprise' => $entreprise === null ? null : [
                'id' => $entreprise->getId(),
                'name' => $entreprise->getName(),
                'slug' => $entreprise->getSlug(),
            ],
        ];
    }

    private function formatAuthor(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $email = $user->getUserIdentifier();
        $at = strpos($email, '@');
        if ($at === false) {
            return $email;
        }

        return substr($email, 0, $at);
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $int = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($int) && $int > 0 ? $int : null;
    }
}
