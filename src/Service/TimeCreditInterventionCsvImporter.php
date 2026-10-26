<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Entity\User;
use App\Repository\TimeCreditRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import massif d’interventions crédits temps depuis un CSV.
 *
 * Colonnes attendues (en-têtes souples) : dossier, durée, date, descriptif.
 */
final class TimeCreditInterventionCsvImporter
{
    public function __construct(
        private readonly TimeCreditRepository $timeCreditRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *   imported: int,
     *   skipped: int,
     *   errors: list<string>,
     *   successes: list<string>
     * }
     */
    public function import(
        Entreprise $entreprise,
        string $csvContent,
        User $actor,
        bool $dryRun = false,
        bool $allowOverdraft = false,
    ): array {
        try {
            $rows = $this->parseCsv($csvContent);
        } catch (\InvalidArgumentException $e) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [$e->getMessage()],
                'successes' => [],
            ];
        }

        if ($rows === []) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Aucune ligne de données dans le CSV.'],
                'successes' => [],
            ];
        }

        return $this->importRows($entreprise, $rows, $actor, $dryRun, $allowOverdraft);
    }

    /**
     * Enregistre des interventions depuis des lignes structurées (tableau UI ou CSV parsé).
     *
     * @param list<array{line?: int, dossier: string, duree: string, date: string|\DateTimeInterface, descriptif: string}> $rows
     *
     * @return array{
     *   imported: int,
     *   skipped: int,
     *   errors: list<string>,
     *   successes: list<string>
     * }
     */
    public function importRows(
        Entreprise $entreprise,
        array $rows,
        User $actor,
        bool $dryRun = false,
        bool $allowOverdraft = false,
    ): array {
        $imported = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<string> $successes */
        $successes = [];

        if ($rows === []) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Aucune intervention à enregistrer.'],
                'successes' => [],
            ];
        }

        $creditsByDossier = $this->indexCreditsByDossier($entreprise);

        /** @var list<array{line: int, dossier: string, minutes: int, occurredAt: \DateTimeImmutable, description: string}> $prepared */
        $prepared = [];
        foreach ($rows as $index => $row) {
            $line = isset($row['line']) ? (int) $row['line'] : ($index + 1);
            $dossier = $this->normalizeDossier((string) ($row['dossier'] ?? ''));
            if ($dossier === '') {
                $errors[] = sprintf('Ligne %d : dossier manquant.', $line);
                ++$skipped;
                continue;
            }

            $dureeRaw = (string) ($row['duree'] ?? '');
            $minutes = $this->parseDurationMinutes($dureeRaw);
            if ($minutes === null || $minutes <= 0) {
                $errors[] = sprintf('Ligne %d : durée invalide « %s ».', $line, $dureeRaw);
                ++$skipped;
                continue;
            }

            $dateRaw = $row['date'] ?? '';
            if ($dateRaw instanceof \DateTimeInterface) {
                $occurredAt = \DateTimeImmutable::createFromInterface($dateRaw)->setTime(0, 0);
            } else {
                $occurredAt = $this->parseDate((string) $dateRaw);
            }
            if (!$occurredAt instanceof \DateTimeImmutable) {
                $errors[] = sprintf(
                    'Ligne %d : date invalide « %s » (formats acceptés : JJ/MM/AAAA, AAAA-MM-JJ).',
                    $line,
                    is_scalar($dateRaw) ? (string) $dateRaw : '',
                );
                ++$skipped;
                continue;
            }

            $description = trim((string) ($row['descriptif'] ?? ''));
            if ($description === '') {
                $errors[] = sprintf('Ligne %d : descriptif manquant.', $line);
                ++$skipped;
                continue;
            }

            $prepared[] = [
                'line' => $line,
                'dossier' => $dossier,
                'minutes' => $minutes,
                'occurredAt' => $occurredAt,
                'description' => mb_substr($description, 0, 2000),
            ];
        }

        usort(
            $prepared,
            static function (array $a, array $b): int {
                $byDate = $a['occurredAt'] <=> $b['occurredAt'];
                if ($byDate !== 0) {
                    return $byDate;
                }

                return $a['line'] <=> $b['line'];
            },
        );

        /** @var array<int, int> $remainingByCreditId solde simulé pendant l’import */
        $remainingByCreditId = [];
        foreach ($creditsByDossier as $credits) {
            foreach ($credits as $credit) {
                $id = $credit->getId();
                if ($id !== null) {
                    $remainingByCreditId[$id] = $credit->getRemainingMinutes();
                }
            }
        }

        foreach ($prepared as $row) {
            $line = $row['line'];
            $credit = $this->resolveCredit($creditsByDossier, $row['dossier']);
            if (!$credit instanceof TimeCredit) {
                $errors[] = sprintf('Ligne %d : aucun crédit trouvé pour le dossier « %s ».', $line, $row['dossier']);
                ++$skipped;
                continue;
            }

            $creditId = $credit->getId() ?? 0;
            $remaining = $remainingByCreditId[$creditId] ?? $credit->getRemainingMinutes();
            if (!$allowOverdraft && $row['minutes'] > $remaining) {
                $errors[] = sprintf(
                    'Ligne %d : durée %d min supérieure au solde restant (%d min) du crédit « %s » (dossier %s).',
                    $line,
                    $row['minutes'],
                    $remaining,
                    $credit->getTitle(),
                    $row['dossier'],
                );
                ++$skipped;
                continue;
            }

            $message = sprintf(
                'Ligne %d : %s — %d min le %s → crédit « %s »',
                $line,
                $row['description'],
                $row['minutes'],
                $row['occurredAt']->format('d/m/Y'),
                $credit->getTitle(),
            );

            if ($dryRun) {
                $successes[] = $message;
                $remainingByCreditId[$creditId] = $remaining - $row['minutes'];
                ++$imported;
                continue;
            }

            $movement = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($actor)
                ->setType(TimeCreditMovement::TYPE_INTERVENTION)
                ->setDeltaMinutes(-$row['minutes'])
                ->setDescription($row['description'])
                ->setOccurredAt($row['occurredAt']);

            $newRemaining = $remaining - $row['minutes'];
            $credit->setRemainingMinutes($newRemaining);
            if ($newRemaining <= 0) {
                $credit->setArchived(true);
            }
            $credit->addMovement($movement);
            $remainingByCreditId[$creditId] = $newRemaining;

            $successes[] = $message;
            ++$imported;
        }

        if (!$dryRun && $imported > 0) {
            $this->entityManager->flush();
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'successes' => $successes,
        ];
    }

    /**
     * Enregistre des interventions avec crédit temps déjà choisi (tableau UI multi-clients).
     *
     * @param list<array{line?: int, timeCredit: TimeCredit, duree: string, date: string|\DateTimeInterface, descriptif: string, entreprise?: ?Entreprise}> $rows
     *
     * @return array{
     *   imported: int,
     *   skipped: int,
     *   errors: list<string>,
     *   successes: list<string>
     * }
     */
    public function importCreditRows(
        array $rows,
        User $actor,
        bool $dryRun = false,
        bool $allowOverdraft = false,
    ): array {
        $imported = 0;
        $skipped = 0;
        /** @var list<string> $errors */
        $errors = [];
        /** @var list<string> $successes */
        $successes = [];

        if ($rows === []) {
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => ['Aucune intervention à enregistrer.'],
                'successes' => [],
            ];
        }

        /** @var list<array{line: int, credit: TimeCredit, minutes: int, occurredAt: \DateTimeImmutable, description: string}> $prepared */
        $prepared = [];
        foreach ($rows as $index => $row) {
            $line = isset($row['line']) ? (int) $row['line'] : ($index + 1);
            $credit = $row['timeCredit'] ?? null;
            if (!$credit instanceof TimeCredit) {
                $errors[] = sprintf('Ligne %d : crédit temps manquant.', $line);
                ++$skipped;
                continue;
            }

            $entreprise = $row['entreprise'] ?? null;
            if ($entreprise instanceof Entreprise && $credit->getEntreprise()?->getId() !== $entreprise->getId()) {
                $errors[] = sprintf(
                    'Ligne %d : le crédit « %s » n’appartient pas au client « %s ».',
                    $line,
                    $credit->getTitle(),
                    $entreprise->getName(),
                );
                ++$skipped;
                continue;
            }

            $dureeRaw = (string) ($row['duree'] ?? '');
            $minutes = $this->parseDurationMinutes($dureeRaw);
            if ($minutes === null || $minutes <= 0) {
                $errors[] = sprintf('Ligne %d : durée invalide « %s ».', $line, $dureeRaw);
                ++$skipped;
                continue;
            }

            $dateRaw = $row['date'] ?? '';
            if ($dateRaw instanceof \DateTimeInterface) {
                $occurredAt = \DateTimeImmutable::createFromInterface($dateRaw)->setTime(0, 0);
            } else {
                $occurredAt = $this->parseDate((string) $dateRaw);
            }
            if (!$occurredAt instanceof \DateTimeImmutable) {
                $errors[] = sprintf(
                    'Ligne %d : date invalide « %s ».',
                    $line,
                    is_scalar($dateRaw) ? (string) $dateRaw : '',
                );
                ++$skipped;
                continue;
            }

            $description = trim((string) ($row['descriptif'] ?? ''));
            if ($description === '') {
                $errors[] = sprintf('Ligne %d : descriptif manquant.', $line);
                ++$skipped;
                continue;
            }

            $prepared[] = [
                'line' => $line,
                'credit' => $credit,
                'minutes' => $minutes,
                'occurredAt' => $occurredAt,
                'description' => mb_substr($description, 0, 2000),
            ];
        }

        usort(
            $prepared,
            static function (array $a, array $b): int {
                $byDate = $a['occurredAt'] <=> $b['occurredAt'];
                if ($byDate !== 0) {
                    return $byDate;
                }

                return $a['line'] <=> $b['line'];
            },
        );

        /** @var array<int, int> $remainingByCreditId */
        $remainingByCreditId = [];
        foreach ($prepared as $row) {
            $creditId = $row['credit']->getId() ?? 0;
            if (!isset($remainingByCreditId[$creditId])) {
                $remainingByCreditId[$creditId] = $row['credit']->getRemainingMinutes();
            }
        }

        foreach ($prepared as $row) {
            $line = $row['line'];
            $credit = $row['credit'];
            $creditId = $credit->getId() ?? 0;
            $remaining = $remainingByCreditId[$creditId] ?? $credit->getRemainingMinutes();

            if (!$allowOverdraft && $row['minutes'] > $remaining) {
                $errors[] = sprintf(
                    'Ligne %d : durée %d min supérieure au solde restant (%d min) du crédit « %s ».',
                    $line,
                    $row['minutes'],
                    $remaining,
                    $credit->getTitle(),
                );
                ++$skipped;
                continue;
            }

            $clientName = $credit->getEntreprise()?->getName() ?? '—';
            $message = sprintf(
                'Ligne %d : %s — %d min le %s → %s / « %s »',
                $line,
                $row['description'],
                $row['minutes'],
                $row['occurredAt']->format('d/m/Y'),
                $clientName,
                $credit->getTitle(),
            );

            if ($dryRun) {
                $successes[] = $message;
                $remainingByCreditId[$creditId] = $remaining - $row['minutes'];
                ++$imported;
                continue;
            }

            $movement = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($actor)
                ->setType(TimeCreditMovement::TYPE_INTERVENTION)
                ->setDeltaMinutes(-$row['minutes'])
                ->setDescription($row['description'])
                ->setOccurredAt($row['occurredAt']);

            $newRemaining = $remaining - $row['minutes'];
            $credit->setRemainingMinutes($newRemaining);
            if ($newRemaining <= 0) {
                $credit->setArchived(true);
            }
            $credit->addMovement($movement);
            $remainingByCreditId[$creditId] = $newRemaining;

            $successes[] = $message;
            ++$imported;
        }

        if (!$dryRun && $imported > 0) {
            $this->entityManager->flush();
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'successes' => $successes,
        ];
    }

    /**
     * @return list<array{line: int, dossier: string, duree: string, date: string, descriptif: string}>
     */
    private function parseCsv(string $csvContent): array
    {
        $csvContent = $this->stripBom($csvContent);
        if (trim($csvContent) === '') {
            throw new \InvalidArgumentException('Le fichier CSV est vide.');
        }

        $delimiter = $this->detectDelimiter($csvContent);
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \InvalidArgumentException('Impossible de lire le CSV.');
        }

        fwrite($handle, $csvContent);
        rewind($handle);

        $header = fgetcsv($handle, 0, $delimiter);
        if ($header === false || $header === [null] || $header === []) {
            fclose($handle);
            throw new \InvalidArgumentException('En-tête CSV manquant.');
        }

        $map = $this->mapHeaders($header);
        if (!isset($map['dossier'], $map['duree'], $map['date'], $map['descriptif'])) {
            fclose($handle);
            throw new \InvalidArgumentException(
                'Colonnes requises : dossier, duree (ou durée), date, descriptif. Séparateur ; ou ,.',
            );
        }

        $rows = [];
        $lineNumber = 1;
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            ++$lineNumber;
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $rows[] = [
                'line' => $lineNumber,
                'dossier' => trim((string) ($data[$map['dossier']] ?? '')),
                'duree' => trim((string) ($data[$map['duree']] ?? '')),
                'date' => trim((string) ($data[$map['date']] ?? '')),
                'descriptif' => trim((string) ($data[$map['descriptif']] ?? '')),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param list<string|null> $header
     *
     * @return array<string, int>
     */
    private function mapHeaders(array $header): array
    {
        $aliases = [
            'dossier' => ['dossier', 'dossier_number', 'numero_dossier', 'n° dossier', 'n de dossier', 'no dossier', 'n°dossier'],
            'duree' => ['duree', 'durée', 'duration', 'minutes', 'temps'],
            'date' => ['date', 'occurred_at', 'date_intervention', 'jour'],
            'descriptif' => ['descriptif', 'description', 'desc', 'libelle', 'libellé', 'commentaire'],
        ];

        $map = [];
        foreach ($header as $index => $label) {
            $normalized = $this->normalizeHeader((string) $label);
            foreach ($aliases as $key => $names) {
                if (\in_array($normalized, $names, true) && !isset($map[$key])) {
                    $map[$key] = $index;
                }
            }
        }

        return $map;
    }

    private function normalizeHeader(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $label = str_replace(["\u{00A0}", '_'], [' ', ' '], $label);
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;

        return $label;
    }

    private function detectDelimiter(string $csvContent): string
    {
        $firstLine = strtok($csvContent, "\r\n") ?: '';
        $semicolons = substr_count($firstLine, ';');
        $commas = substr_count($firstLine, ',');

        return $semicolons >= $commas ? ';' : ',';
    }

    private function stripBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    /**
     * @param list<string|null> $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeDossier(string $dossier): string
    {
        return trim(preg_replace('/\s+/', ' ', $dossier) ?? $dossier);
    }

    private function parseDurationMinutes(string $raw): ?int
    {
        $value = mb_strtolower(trim($raw));
        $value = str_replace(["\u{00A0}", ','], ['', '.'], $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*h\s*(\d{1,2})?$/', $value, $matches) === 1) {
            $hours = (int) $matches[1];
            $minutes = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
            if ($minutes >= 60) {
                return null;
            }

            return ($hours * 60) + $minutes;
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h$/', $value, $matches) === 1) {
            return (int) round(((float) $matches[1]) * 60);
        }

        if (preg_match('/^(\d+)\s*(?:mn|min|minutes?)?$/', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        return null;
    }

    private function parseDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $raw);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date instanceof \DateTimeImmutable
                && ($errors === false || (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0))
            ) {
                return $date;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<TimeCredit>>
     */
    private function indexCreditsByDossier(Entreprise $entreprise): array
    {
        $credits = $this->timeCreditRepository->findByEntrepriseOrdered($entreprise);
        $indexed = [];
        foreach ($credits as $credit) {
            $dossier = $this->normalizeDossier((string) $credit->getDossierNumber());
            if ($dossier === '') {
                continue;
            }
            $key = mb_strtolower($dossier);
            $indexed[$key] ??= [];
            $indexed[$key][] = $credit;
        }

        return $indexed;
    }

    /**
     * @param array<string, list<TimeCredit>> $creditsByDossier
     */
    private function resolveCredit(array $creditsByDossier, string $dossier): ?TimeCredit
    {
        $key = mb_strtolower($this->normalizeDossier($dossier));
        $candidates = $creditsByDossier[$key] ?? [];
        if ($candidates === []) {
            return null;
        }

        if (\count($candidates) === 1) {
            return $candidates[0];
        }

        usort(
            $candidates,
            static function (TimeCredit $a, TimeCredit $b): int {
                // Préférer non archivé, puis solde > 0, puis plus récent.
                $aScore = ($a->isArchived() ? 0 : 2) + ($a->getRemainingMinutes() > 0 ? 1 : 0);
                $bScore = ($b->isArchived() ? 0 : 2) + ($b->getRemainingMinutes() > 0 ? 1 : 0);
                if ($aScore !== $bScore) {
                    return $bScore <=> $aScore;
                }

                return $b->getCreatedAt() <=> $a->getCreatedAt();
            },
        );

        return $candidates[0];
    }
}
