<?php

declare(strict_types=1);

namespace App\Entreprise;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditCategory;
use App\Entity\TimeCreditMovement;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditRepository;
use App\Service\TimeCreditBalanceRecalculator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import manuel (cas par cas) depuis les catalogues JSON anciennes données.
 */
final class LegacySelectiveImporter
{
    public function __construct(
        private readonly LegacyCatalogStorage $catalogStorage,
        private readonly LegacyEntrepriseExchange $entrepriseExchange,
        private readonly LegacyTimeCreditExchange $timeCreditExchange,
        private readonly EntrepriseRepository $entrepriseRepository,
        private readonly TimeCreditRepository $timeCreditRepository,
        private readonly EntrepriseSlugGenerator $slugGenerator,
        private readonly TimeCreditBalanceRecalculator $balanceRecalculator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<array{
     *     legacySourceId: int,
     *     name: string,
     *     archived: bool,
     *     status: 'available'|'imported'|'name_conflict',
     *     matchedEntreprise: ?Entreprise
     * }>
     */
    public function listEntreprises(?string $search = null): array
    {
        $payload = $this->entrepriseExchange->decodeJson($this->catalogStorage->readEntreprisesJson());
        $search = $search !== null ? mb_strtolower(trim($search)) : '';
        $rows = [];

        foreach ($payload['entreprises'] as $item) {
            if (!empty($item['archived'])) {
                // On liste aussi les archivées, marquées clairement.
            }
            $name = $item['name'];
            if ($search !== '' && !str_contains(mb_strtolower($name), $search)) {
                continue;
            }

            $bySource = $this->entrepriseRepository->findOneByLegacySourceId($item['legacySourceId']);
            $byName = $this->entrepriseRepository->findOneByName($name);

            if ($bySource instanceof Entreprise) {
                $status = 'imported';
                $matched = $bySource;
            } elseif ($byName instanceof Entreprise) {
                $status = 'name_conflict';
                $matched = $byName;
            } else {
                $status = 'available';
                $matched = null;
            }

            $rows[] = [
                'legacySourceId' => $item['legacySourceId'],
                'name' => $name,
                'archived' => (bool) ($item['archived'] ?? false),
                'status' => $status,
                'matchedEntreprise' => $matched,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @return array{legacySourceId: int, name: string, archived: bool}
     */
    public function getEntrepriseItem(int $legacySourceId): array
    {
        $payload = $this->entrepriseExchange->decodeJson($this->catalogStorage->readEntreprisesJson());
        foreach ($payload['entreprises'] as $item) {
            if ((int) $item['legacySourceId'] === $legacySourceId) {
                return [
                    'legacySourceId' => (int) $item['legacySourceId'],
                    'name' => $item['name'],
                    'archived' => (bool) ($item['archived'] ?? false),
                ];
            }
        }

        throw new \InvalidArgumentException(sprintf('Entreprise source #%d introuvable dans le catalogue.', $legacySourceId));
    }

    public function importEntreprise(int $legacySourceId, bool $overwriteNameConflict = false): Entreprise
    {
        $item = $this->getEntrepriseItem($legacySourceId);
        $existingBySource = $this->entrepriseRepository->findOneByLegacySourceId($legacySourceId);
        if ($existingBySource instanceof Entreprise) {
            return $existingBySource;
        }

        $existingByName = $this->entrepriseRepository->findOneByName($item['name']);
        if ($existingByName instanceof Entreprise) {
            if (!$overwriteNameConflict) {
                throw new \RuntimeException(sprintf(
                    'Une entreprise « %s » existe déjà (#%d). Confirmez l’écrasement pour la lier à l’ancienne source.',
                    $existingByName->getName(),
                    $existingByName->getId(),
                ));
            }

            if ($existingByName->getLegacySourceId() !== null && $existingByName->getLegacySourceId() !== $legacySourceId) {
                throw new \RuntimeException('Cette entreprise est déjà liée à une autre source ancienne.');
            }

            $existingByName
                ->setLegacySourceId($legacySourceId)
                ->setLegacy(false)
                ->setAgency(false);
            $this->entityManager->flush();

            return $existingByName;
        }

        $entreprise = (new Entreprise())
            ->setName($item['name'])
            ->setSlug($this->slugGenerator->generateUniqueSlug($item['name']))
            ->setAgency(false)
            ->setLegacy(false)
            ->setLegacySourceId($legacySourceId);

        $this->entityManager->persist($entreprise);
        $this->entityManager->flush();

        return $entreprise;
    }

    /**
     * @return list<array{
     *     legacySourceId: int,
     *     entrepriseLegacySourceId: int,
     *     entrepriseName: string,
     *     totalMinutes: int,
     *     creditedAt: string,
     *     interventionCount: int,
     *     usedMinutes: int,
     *     remainingMinutes: int,
     *     status: 'available'|'imported'|'entreprise_missing',
     *     matchedCredit: ?TimeCredit,
     *     matchedEntreprise: ?Entreprise
     * }>
     */
    public function listTimeCredits(?string $search = null): array
    {
        $payload = $this->timeCreditExchange->decodeJson($this->catalogStorage->readTimeCreditsJson());
        $entreprises = [];
        if ($this->catalogStorage->hasEntreprisesCatalog()) {
            $entreprisesPayload = $this->entrepriseExchange->decodeJson($this->catalogStorage->readEntreprisesJson());
            foreach ($entreprisesPayload['entreprises'] as $e) {
                $entreprises[(int) $e['legacySourceId']] = $e['name'];
            }
        }

        $search = $search !== null ? mb_strtolower(trim($search)) : '';
        $rows = [];

        foreach ($payload['credits'] as $item) {
            $entrepriseName = $entreprises[$item['entrepriseLegacySourceId']] ?? sprintf('Entreprise #%d', $item['entrepriseLegacySourceId']);
            if ($search !== ''
                && !str_contains(mb_strtolower($entrepriseName), $search)
                && !str_contains((string) $item['legacySourceId'], $search)
            ) {
                continue;
            }

            $used = 0;
            foreach ($item['interventions'] as $intervention) {
                $used += (int) $intervention['minutes'];
            }
            $total = (int) $item['totalMinutes'];
            $remaining = max(0, $total - $used);

            $bySource = $this->timeCreditRepository->findOneByLegacySourceId($item['legacySourceId']);
            $entreprise = $this->entrepriseRepository->findOneByLegacySourceId($item['entrepriseLegacySourceId']);

            if ($bySource instanceof TimeCredit) {
                $status = 'imported';
            } elseif (!$entreprise instanceof Entreprise) {
                $status = 'entreprise_missing';
            } else {
                $status = 'available';
            }

            $rows[] = [
                'legacySourceId' => $item['legacySourceId'],
                'entrepriseLegacySourceId' => $item['entrepriseLegacySourceId'],
                'entrepriseName' => $entrepriseName,
                'totalMinutes' => $total,
                'creditedAt' => $item['creditedAt'],
                'interventionCount' => \count($item['interventions']),
                'usedMinutes' => $used,
                'remainingMinutes' => $remaining,
                'status' => $status,
                'matchedCredit' => $bySource,
                'matchedEntreprise' => $entreprise,
            ];
        }

        usort($rows, static fn (array $a, array $b): int => strcasecmp($a['entrepriseName'], $b['entrepriseName']));

        return $rows;
    }

    /**
     * @return array{
     *     legacySourceId: int,
     *     entrepriseLegacySourceId: int,
     *     totalMinutes: int,
     *     creditedAt: string,
     *     interventions: list<array{legacySourceId?: int, minutes: int, description?: string, occurredAt: string}>
     * }
     */
    public function getTimeCreditItem(int $legacySourceId): array
    {
        $payload = $this->timeCreditExchange->decodeJson($this->catalogStorage->readTimeCreditsJson());
        foreach ($payload['credits'] as $item) {
            if ((int) $item['legacySourceId'] === $legacySourceId) {
                return $item;
            }
        }

        throw new \InvalidArgumentException(sprintf('Crédit source #%d introuvable dans le catalogue.', $legacySourceId));
    }

    public function importTimeCredit(
        int $legacySourceId,
        User $createdBy,
        string $title,
        ?TimeCreditCategory $category,
        ?string $dossierNumber,
        ?string $siteUrl,
        ?Entreprise $overrideEntreprise = null,
    ): TimeCredit {
        $existing = $this->timeCreditRepository->findOneByLegacySourceId($legacySourceId);
        if ($existing instanceof TimeCredit) {
            return $existing;
        }

        $item = $this->getTimeCreditItem($legacySourceId);
        $entreprise = $overrideEntreprise;
        if (!$entreprise instanceof Entreprise) {
            $entreprise = $this->entrepriseRepository->findOneByLegacySourceId($item['entrepriseLegacySourceId']);
        }
        if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
            throw new \RuntimeException('Importez d’abord l’entreprise liée, ou choisissez une entreprise cible.');
        }

        $creditedAt = new \DateTimeImmutable($item['creditedAt'].' 12:00:00');
        $title = trim($title);
        if ($title === '') {
            $title = sprintf('Crédit importé du %s', $creditedAt->format('d/m/Y'));
        }

        $credit = (new TimeCredit())
            ->setTitle($title)
            ->setTotalMinutes($item['totalMinutes'])
            ->setRemainingMinutes($item['totalMinutes'])
            ->setArchived(false)
            ->setLegacySourceId($legacySourceId)
            ->setCreatedBy($createdBy)
            ->setCategory($category)
            ->setDossierNumber($dossierNumber)
            ->setSiteUrl($siteUrl);
        $credit->setEntreprise($entreprise);

        $allocation = (new TimeCreditMovement())
            ->setTimeCredit($credit)
            ->setCreatedBy($createdBy)
            ->setType(TimeCreditMovement::TYPE_ALLOCATION)
            ->setDeltaMinutes($item['totalMinutes'])
            ->setDescription('Import ancien extranet (allocation)')
            ->setOccurredAt($creditedAt);
        $credit->addMovement($allocation);

        foreach ($item['interventions'] as $intervention) {
            try {
                $occurredAt = new \DateTimeImmutable($intervention['occurredAt'].' 12:00:00');
            } catch (\Exception) {
                $occurredAt = $creditedAt;
            }
            $description = trim((string) ($intervention['description'] ?? ''));
            $movement = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($createdBy)
                ->setType(TimeCreditMovement::TYPE_INTERVENTION)
                ->setDeltaMinutes(-1 * abs((int) $intervention['minutes']))
                ->setDescription($description !== '' ? $description : 'Intervention importée')
                ->setOccurredAt($occurredAt);
            $credit->addMovement($movement);
        }

        $this->entityManager->persist($credit);
        $this->entityManager->flush();
        $this->balanceRecalculator->recalculate($credit);
        $this->entityManager->flush();

        return $credit;
    }
}
