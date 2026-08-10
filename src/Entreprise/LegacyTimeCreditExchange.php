<?php

declare(strict_types=1);

namespace App\Entreprise;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use App\Repository\TimeCreditRepository;
use App\Service\TimeCreditBalanceRecalculator;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Échange JSON des crédits temps issus de l’ancien extranet.
 */
final class LegacyTimeCreditExchange
{
    public const string FORMAT = 'extranet-17b-legacy-time-credits';
    public const int VERSION = 1;
    private const string CREDIT_TABLE = 'EXTRANETB17_MAINTENANCE_CREDIT';
    private const string INTERVENTION_TABLE = 'EXTRANETB17_MAINTENANCE_INTERVENTION';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TimeCreditRepository $timeCreditRepository,
        private readonly EntrepriseRepository $entrepriseRepository,
        private readonly TimeCreditBalanceRecalculator $balanceRecalculator,
    ) {
    }

    /**
     * @return array{
     *     format: string,
     *     version: int,
     *     exportedAt: string,
     *     source: string,
     *     credits: list<array{
     *         legacySourceId: int,
     *         entrepriseLegacySourceId: int,
     *         totalMinutes: int,
     *         creditedAt: string,
     *         interventions: list<array{
     *             legacySourceId: int,
     *             minutes: int,
     *             description: string,
     *             occurredAt: string
     *         }>
     *     }>
     * }
     */
    public function exportFromDatabase(): array
    {
        $credits = $this->timeCreditRepository->createQueryBuilder('tc')
            ->andWhere('tc.legacySourceId IS NOT NULL')
            ->leftJoin('tc.entreprise', 'e')
            ->addSelect('e')
            ->orderBy('tc.legacySourceId', 'ASC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($credits as $credit) {
            if (!$credit instanceof TimeCredit || $credit->getLegacySourceId() === null) {
                continue;
            }
            $entreprise = $credit->getEntreprise();
            $entrepriseLegacyId = $entreprise?->getLegacySourceId();
            if ($entrepriseLegacyId === null) {
                continue;
            }

            $interventions = [];
            foreach ($credit->getMovements() as $movement) {
                if ($movement->getType() !== TimeCreditMovement::TYPE_INTERVENTION) {
                    continue;
                }
                $interventions[] = [
                    'legacySourceId' => $movement->getId() ?? 0,
                    'minutes' => abs($movement->getDeltaMinutes()),
                    'description' => (string) ($movement->getDescription() ?? ''),
                    'occurredAt' => $movement->getOccurredAt()->format('Y-m-d'),
                ];
            }

            $items[] = [
                'legacySourceId' => $credit->getLegacySourceId(),
                'entrepriseLegacySourceId' => $entrepriseLegacyId,
                'totalMinutes' => $credit->getTotalMinutes(),
                'creditedAt' => $credit->getCreatedAt()->format('Y-m-d'),
                'interventions' => $interventions,
            ];
        }

        return $this->buildPayload($items, 'database');
    }

    /**
     * @param list<array{
     *     legacySourceId: int,
     *     entrepriseLegacySourceId: int,
     *     totalMinutes: int,
     *     creditedAt: string,
     *     interventions: list<array{
     *         legacySourceId: int,
     *         minutes: int,
     *         description: string,
     *         occurredAt: string
     *     }>
     * }> $items
     *
     * @return array{
     *     format: string,
     *     version: int,
     *     exportedAt: string,
     *     source: string,
     *     credits: list<array{
     *         legacySourceId: int,
     *         entrepriseLegacySourceId: int,
     *         totalMinutes: int,
     *         creditedAt: string,
     *         interventions: list<array{
     *             legacySourceId: int,
     *             minutes: int,
     *             description: string,
     *             occurredAt: string
     *         }>
     *     }>
     * }
     */
    public function buildPayload(array $items, string $source): array
    {
        usort($items, static fn (array $a, array $b): int => $a['legacySourceId'] <=> $b['legacySourceId']);

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
            'source' => $source,
            'credits' => array_values($items),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encodeJson(array $payload): string
    {
        return json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }

    /**
     * @return array{
     *     format: string,
     *     version: int,
     *     credits: list<array{
     *         legacySourceId: int,
     *         entrepriseLegacySourceId: int,
     *         totalMinutes: int,
     *         creditedAt: string,
     *         interventions: list<array{
     *             legacySourceId?: int,
     *             minutes: int,
     *             description?: string,
     *             occurredAt: string
     *         }>
     *     }>
     * }
     */
    public function decodeJson(string $json): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('JSON invalide : '.$e->getMessage(), 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new \InvalidArgumentException('Le fichier JSON doit contenir un objet.');
        }

        if (($decoded['format'] ?? null) !== self::FORMAT) {
            throw new \InvalidArgumentException(sprintf(
                'Format attendu « %s », reçu « %s ».',
                self::FORMAT,
                (string) ($decoded['format'] ?? 'inconnu'),
            ));
        }

        if ((int) ($decoded['version'] ?? 0) !== self::VERSION) {
            throw new \InvalidArgumentException('Version de fichier non supportée.');
        }

        if (!isset($decoded['credits']) || !\is_array($decoded['credits'])) {
            throw new \InvalidArgumentException('Clé « credits » manquante ou invalide.');
        }

        $credits = [];
        foreach ($decoded['credits'] as $index => $row) {
            if (!\is_array($row)) {
                throw new \InvalidArgumentException(sprintf('Entrée credits[%d] invalide.', $index));
            }

            $legacySourceId = (int) ($row['legacySourceId'] ?? 0);
            $entrepriseLegacySourceId = (int) ($row['entrepriseLegacySourceId'] ?? 0);
            $totalMinutes = (int) ($row['totalMinutes'] ?? 0);
            $creditedAt = (string) ($row['creditedAt'] ?? '');
            if ($legacySourceId <= 0 || $entrepriseLegacySourceId <= 0 || $totalMinutes <= 0 || $creditedAt === '') {
                throw new \InvalidArgumentException(sprintf('Entrée credits[%d] incomplète.', $index));
            }

            $interventions = [];
            $rawInterventions = $row['interventions'] ?? [];
            if (!\is_array($rawInterventions)) {
                throw new \InvalidArgumentException(sprintf('credits[%d].interventions invalide.', $index));
            }
            foreach ($rawInterventions as $iIndex => $intervention) {
                if (!\is_array($intervention)) {
                    throw new \InvalidArgumentException(sprintf('credits[%d].interventions[%d] invalide.', $index, $iIndex));
                }
                $minutes = (int) ($intervention['minutes'] ?? 0);
                $occurredAt = (string) ($intervention['occurredAt'] ?? '');
                if ($minutes <= 0 || $occurredAt === '') {
                    continue;
                }
                $interventions[] = [
                    'legacySourceId' => (int) ($intervention['legacySourceId'] ?? 0),
                    'minutes' => $minutes,
                    'description' => $this->normalizeDescription((string) ($intervention['description'] ?? '')),
                    'occurredAt' => $occurredAt,
                ];
            }

            $credits[] = [
                'legacySourceId' => $legacySourceId,
                'entrepriseLegacySourceId' => $entrepriseLegacySourceId,
                'totalMinutes' => $totalMinutes,
                'creditedAt' => $creditedAt,
                'interventions' => $interventions,
            ];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'credits' => $credits,
        ];
    }

    /**
     * @param array{
     *     credits: list<array{
     *         legacySourceId: int,
     *         entrepriseLegacySourceId: int,
     *         totalMinutes: int,
     *         creditedAt: string,
     *         interventions: list<array{
     *             legacySourceId?: int,
     *             minutes: int,
     *             description?: string,
     *             occurredAt: string
     *         }>
     *     }>
     * } $payload
     */
    public function importPayload(array $payload, User $createdBy, bool $dryRun = false): LegacyEntrepriseImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($payload['credits'] as $row) {
            $legacySourceId = $row['legacySourceId'];
            $existing = $this->timeCreditRepository->findOneByLegacySourceId($legacySourceId);
            if ($existing instanceof TimeCredit) {
                ++$skipped;
                continue;
            }

            $entreprise = $this->entrepriseRepository->findOneByLegacySourceId($row['entrepriseLegacySourceId']);
            if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
                ++$skipped;
                $skippedReasons[] = sprintf(
                    'crédit #%d : entreprise source #%d introuvable (importez d’abord les entreprises)',
                    $legacySourceId,
                    $row['entrepriseLegacySourceId'],
                );
                continue;
            }

            $creditedAt = $this->parseDate($row['creditedAt']);
            if ($creditedAt === null) {
                ++$skipped;
                $skippedReasons[] = sprintf('crédit #%d : date invalide', $legacySourceId);
                continue;
            }

            if ($dryRun) {
                ++$created;
                continue;
            }

            $credit = (new TimeCredit())
                ->setTitle(sprintf('Crédit importé du %s', $creditedAt->format('d/m/Y')))
                ->setTotalMinutes($row['totalMinutes'])
                ->setRemainingMinutes($row['totalMinutes'])
                ->setArchived(false)
                ->setLegacySourceId($legacySourceId)
                ->setCreatedBy($createdBy);
            $credit->setEntreprise($entreprise);

            $allocation = (new TimeCreditMovement())
                ->setTimeCredit($credit)
                ->setCreatedBy($createdBy)
                ->setType(TimeCreditMovement::TYPE_ALLOCATION)
                ->setDeltaMinutes($row['totalMinutes'])
                ->setDescription('Import ancien extranet (allocation)')
                ->setOccurredAt($creditedAt);
            $credit->addMovement($allocation);

            foreach ($row['interventions'] as $intervention) {
                $occurredAt = $this->parseDate($intervention['occurredAt']) ?? $creditedAt;
                $movement = (new TimeCreditMovement())
                    ->setTimeCredit($credit)
                    ->setCreatedBy($createdBy)
                    ->setType(TimeCreditMovement::TYPE_INTERVENTION)
                    ->setDeltaMinutes(-1 * abs((int) $intervention['minutes']))
                    ->setDescription($this->normalizeDescription((string) ($intervention['description'] ?? '')) ?: 'Intervention importée')
                    ->setOccurredAt($occurredAt);
                $credit->addMovement($movement);
            }

            $this->entityManager->persist($credit);
            $this->entityManager->flush();
            $this->balanceRecalculator->recalculate($credit);
            $this->entityManager->flush();

            ++$created;
        }

        return new LegacyEntrepriseImportResult($created, $updated, $skipped, $skippedReasons);
    }

    /**
     * @return list<array{
     *     legacySourceId: int,
     *     entrepriseLegacySourceId: int,
     *     totalMinutes: int,
     *     creditedAt: string,
     *     interventions: list<array{
     *         legacySourceId: int,
     *         minutes: int,
     *         description: string,
     *         occurredAt: string
     *     }>
     * }>
     */
    public function itemsFromSqlDump(string $dumpPath): array
    {
        if (!is_file($dumpPath) || !is_readable($dumpPath)) {
            throw new \InvalidArgumentException(sprintf('Fichier introuvable ou illisible : %s', $dumpPath));
        }

        $sql = $this->readDumpContents($dumpPath);
        $credits = $this->parseTableRows($sql, self::CREDIT_TABLE, ['id', 'id_entreprise', 'tps_total', 'date_credit']);
        $interventions = $this->parseTableRows($sql, self::INTERVENTION_TABLE, ['id', 'id_entreprise', 'description', 'tps_passe', 'date_intervention']);

        $byEntreprise = [];
        foreach ($interventions as $intervention) {
            $entrepriseId = (int) $intervention['id_entreprise'];
            $minutes = (int) $intervention['tps_passe'];
            $date = $this->normalizeSqlDate($intervention['date_intervention']);
            if ($entrepriseId <= 0 || $minutes <= 0 || $date === null) {
                continue;
            }
            $byEntreprise[$entrepriseId][] = [
                'legacySourceId' => (int) $intervention['id'],
                'minutes' => $minutes,
                'description' => $this->normalizeDescription($intervention['description']),
                'occurredAt' => $date,
            ];
        }

        $items = [];
        foreach ($credits as $credit) {
            $legacySourceId = (int) $credit['id'];
            $entrepriseId = (int) $credit['id_entreprise'];
            $totalMinutes = (int) $credit['tps_total'];
            $creditedAt = $this->normalizeSqlDate($credit['date_credit']);
            if ($legacySourceId <= 0 || $entrepriseId <= 0 || $totalMinutes <= 0 || $creditedAt === null) {
                continue;
            }

            $entsInterventions = $byEntreprise[$entrepriseId] ?? [];
            usort(
                $entsInterventions,
                static fn (array $a, array $b): int => strcmp($a['occurredAt'], $b['occurredAt']) ?: ($a['legacySourceId'] <=> $b['legacySourceId']),
            );

            $items[] = [
                'legacySourceId' => $legacySourceId,
                'entrepriseLegacySourceId' => $entrepriseId,
                'totalMinutes' => $totalMinutes,
                'creditedAt' => $creditedAt,
                'interventions' => $entsInterventions,
            ];
        }

        return $items;
    }

    private function readDumpContents(string $path): string
    {
        $raw = str_ends_with(mb_strtolower($path), '.gz')
            ? (string) gzdecode((string) file_get_contents($path))
            : (string) file_get_contents($path);

        if ($raw === '') {
            throw new \RuntimeException('Dump vide ou compression invalide.');
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
            if (\is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return $raw;
    }

    /**
     * @param list<string> $requiredColumns
     *
     * @return list<array<string, string>>
     */
    private function parseTableRows(string $sql, string $table, array $requiredColumns): array
    {
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'`\s*\(([^)]+)\)\s*VALUES\s*(.*?);/is';
        if (!preg_match_all($pattern, $sql, $matches, \PREG_SET_ORDER)) {
            throw new \RuntimeException(sprintf('Aucun INSERT trouvé pour %s.', $table));
        }

        $rows = [];
        foreach ($matches as $match) {
            $columns = array_map(
                static fn (string $col): string => trim($col, " \t\n\r\0\x0B`"),
                explode(',', $match[1]),
            );
            foreach ($this->parseValueTuples($match[2]) as $fields) {
                if (\count($fields) !== \count($columns)) {
                    continue;
                }
                $assoc = array_combine($columns, $fields);
                if ($assoc === false) {
                    continue;
                }
                $row = [];
                foreach ($requiredColumns as $column) {
                    $row[$column] = $this->unquoteSql($assoc[$column] ?? '');
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function parseValueTuples(string $valuesBlob): array
    {
        $s = trim($valuesBlob);
        if ($s === '') {
            return [];
        }
        if ($s[0] === '(') {
            $s = substr($s, 1);
        }
        if (str_ends_with($s, ')')) {
            $s = substr($s, 0, -1);
        }

        $tuples = [];
        $fields = [];
        $field = '';
        $depth = 1;
        $inString = false;
        $length = \strlen($s);

        for ($i = 0; $i < $length; ++$i) {
            $ch = $s[$i];

            if ($inString) {
                $field .= $ch;
                if ($ch === '\\' && $i + 1 < $length) {
                    $field .= $s[$i + 1];
                    ++$i;
                    continue;
                }
                if ($ch === "'") {
                    $inString = false;
                }
                continue;
            }

            if ($ch === "'") {
                $inString = true;
                $field .= $ch;
                continue;
            }

            if ($ch === ',' && $depth === 1) {
                $fields[] = trim($field);
                $field = '';
                continue;
            }

            if ($ch === '(') {
                ++$depth;
                $field .= $ch;
                continue;
            }

            if ($ch === ')') {
                --$depth;
                if ($depth === 0) {
                    $fields[] = trim($field);
                    $tuples[] = $fields;
                    $fields = [];
                    $field = '';
                    while ($i + 1 < $length && str_contains(" \n\r\t,", $s[$i + 1])) {
                        ++$i;
                    }
                    if ($i + 1 < $length && $s[$i + 1] === '(') {
                        ++$i;
                        $depth = 1;
                    }
                    continue;
                }
                $field .= $ch;
                continue;
            }

            $field .= $ch;
        }

        return $tuples;
    }

    private function unquoteSql(string $value): string
    {
        $value = trim($value);
        if (strcasecmp($value, 'NULL') === 0) {
            return '';
        }
        if (\strlen($value) >= 2 && $value[0] === "'" && str_ends_with($value, "'")) {
            return stripcslashes(substr($value, 1, -1));
        }

        return $value;
    }

    private function normalizeSqlDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m)) {
            return null;
        }

        return substr($m[0], 0, 10);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $normalized = $this->normalizeSqlDate($value);
        if ($normalized === null) {
            return null;
        }

        try {
            return new \DateTimeImmutable($normalized.' 12:00:00');
        } catch (\Exception) {
            return null;
        }
    }

    private function normalizeDescription(string $description): string
    {
        $description = trim(html_entity_decode($description, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $description = (string) preg_replace('/\s+/u', ' ', $description);
        if (mb_strlen($description) > 2000) {
            $description = mb_substr($description, 0, 2000);
        }

        return $description;
    }
}
