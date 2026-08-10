<?php

declare(strict_types=1);

namespace App\Entreprise;

use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Échange JSON des entreprises issues de l’ancien extranet (flag legacy).
 */
final class LegacyEntrepriseExchange
{
    public const string FORMAT = 'extranet-17b-legacy-entreprises';
    public const int VERSION = 1;
    private const string SQL_TABLE = 'EXTRANETB17_ENTREPRISE';
    private const int AGENCY_OLD_ID = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntrepriseRepository $entrepriseRepository,
        private readonly EntrepriseSlugGenerator $slugGenerator,
    ) {
    }

    /**
     * @return array{
     *     format: string,
     *     version: int,
     *     exportedAt: string,
     *     source: string,
     *     entreprises: list<array{legacySourceId: int, name: string, archived: bool}>
     * }
     */
    public function exportFromDatabase(): array
    {
        $entreprises = $this->entrepriseRepository->createQueryBuilder('e')
            ->andWhere('e.legacy = :legacy')
            ->setParameter('legacy', true)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();

        $items = [];
        foreach ($entreprises as $entreprise) {
            if (!$entreprise instanceof Entreprise || $entreprise->getLegacySourceId() === null) {
                continue;
            }
            $items[] = [
                'legacySourceId' => $entreprise->getLegacySourceId(),
                'name' => $entreprise->getName(),
                'archived' => false,
            ];
        }

        return $this->buildPayload($items, 'database');
    }

    /**
     * @param list<array{legacySourceId: int, name: string, archived: bool}> $items
     *
     * @return array{
     *     format: string,
     *     version: int,
     *     exportedAt: string,
     *     source: string,
     *     entreprises: list<array{legacySourceId: int, name: string, archived: bool}>
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
            'entreprises' => array_values($items),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function encodeJson(array $payload): string
    {
        $json = json_encode($payload, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        return $json."\n";
    }

    /**
     * @return array{
     *     format: string,
     *     version: int,
     *     exportedAt?: string,
     *     source?: string,
     *     entreprises: list<array{legacySourceId: int, name: string, archived?: bool}>
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

        $version = (int) ($decoded['version'] ?? 0);
        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException(sprintf('Version de fichier non supportée : %d.', $version));
        }

        if (!isset($decoded['entreprises']) || !\is_array($decoded['entreprises'])) {
            throw new \InvalidArgumentException('Clé « entreprises » manquante ou invalide.');
        }

        $items = [];
        foreach ($decoded['entreprises'] as $index => $row) {
            if (!\is_array($row)) {
                throw new \InvalidArgumentException(sprintf('Entrée entreprises[%d] invalide.', $index));
            }
            $id = (int) ($row['legacySourceId'] ?? 0);
            $name = $this->normalizeName((string) ($row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                throw new \InvalidArgumentException(sprintf('Entrée entreprises[%d] incomplète (id/nom).', $index));
            }
            $items[] = [
                'legacySourceId' => $id,
                'name' => $name,
                'archived' => (bool) ($row['archived'] ?? false),
            ];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exportedAt' => isset($decoded['exportedAt']) ? (string) $decoded['exportedAt'] : '',
            'source' => isset($decoded['source']) ? (string) $decoded['source'] : '',
            'entreprises' => $items,
        ];
    }

    /**
     * @param array{
     *     entreprises: list<array{legacySourceId: int, name: string, archived?: bool}>
     * } $payload
     */
    public function importPayload(array $payload, bool $includeArchived = false, bool $dryRun = false): LegacyEntrepriseImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skippedReasons = [];

        foreach ($payload['entreprises'] as $row) {
            $oldId = $row['legacySourceId'];
            $name = $this->normalizeName($row['name']);
            $archived = (bool) ($row['archived'] ?? false);

            if ($oldId === self::AGENCY_OLD_ID) {
                ++$skipped;
                $skippedReasons[] = sprintf('#%d « %s » : agence B17 (ignorée)', $oldId, $name);

                continue;
            }

            if ($archived && !$includeArchived) {
                ++$skipped;

                continue;
            }

            if ($name === '') {
                ++$skipped;
                $skippedReasons[] = sprintf('#%d : nom vide', $oldId);

                continue;
            }

            $existingBySource = $this->entrepriseRepository->findOneByLegacySourceId($oldId);
            if ($existingBySource instanceof Entreprise) {
                $needsNameUpdate = $existingBySource->getName() !== $name
                    && !$this->entrepriseRepository->existsByName($name, $existingBySource->getId());
                $needsLegacyFlag = !$existingBySource->isLegacy();

                if ($needsNameUpdate || $needsLegacyFlag) {
                    if (!$dryRun) {
                        if ($needsNameUpdate) {
                            $existingBySource->setName($name);
                        }
                        if ($needsLegacyFlag) {
                            $existingBySource->setLegacy(true);
                        }
                    }
                    ++$updated;
                } else {
                    ++$skipped;
                }

                continue;
            }

            if ($this->entrepriseRepository->existsByName($name)) {
                ++$skipped;
                $skippedReasons[] = sprintf('#%d « %s » : nom déjà présent', $oldId, $name);

                continue;
            }

            if (!$dryRun) {
                $entreprise = (new Entreprise())
                    ->setName($name)
                    ->setSlug($this->slugGenerator->generateUniqueSlug($name))
                    ->setAgency(false)
                    ->setLegacy(true)
                    ->setLegacySourceId($oldId);
                $this->entityManager->persist($entreprise);
                $this->entityManager->flush();
            }

            ++$created;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return new LegacyEntrepriseImportResult($created, $updated, $skipped, $skippedReasons);
    }

    /**
     * Construit les items d’import depuis un dump SQL/SQL.GZ de l’ancien site.
     *
     * @return list<array{legacySourceId: int, name: string, archived: bool}>
     */
    public function itemsFromSqlDump(string $dumpPath, bool $includeArchived = false): array
    {
        if (!is_file($dumpPath) || !is_readable($dumpPath)) {
            throw new \InvalidArgumentException(sprintf('Fichier introuvable ou illisible : %s', $dumpPath));
        }

        $sql = $this->readDumpContents($dumpPath);
        $rows = $this->parseEntrepriseRows($sql);
        $items = [];

        foreach ($rows as $row) {
            $oldId = (int) $row['id_ent'];
            $name = $this->normalizeName($row['nom_entreprise']);
            $archived = ((int) $row['archive']) === 1;

            if ($oldId === self::AGENCY_OLD_ID || $name === '') {
                continue;
            }
            if ($archived && !$includeArchived) {
                continue;
            }

            $items[] = [
                'legacySourceId' => $oldId,
                'name' => $name,
                'archived' => $archived,
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
     * @return list<array{id_ent: string, nom_entreprise: string, archive: string}>
     */
    private function parseEntrepriseRows(string $sql): array
    {
        $pattern = '/INSERT INTO `'.preg_quote(self::SQL_TABLE, '/').'`\s*\(([^)]+)\)\s*VALUES\s*(.*?);/is';
        if (!preg_match_all($pattern, $sql, $matches, \PREG_SET_ORDER)) {
            throw new \RuntimeException(sprintf('Aucun INSERT trouvé pour %s.', self::SQL_TABLE));
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
                $rows[] = [
                    'id_ent' => $this->unquoteSql($assoc['id_ent'] ?? ''),
                    'nom_entreprise' => $this->unquoteSql($assoc['nom_entreprise'] ?? ''),
                    'archive' => $this->unquoteSql($assoc['archive'] ?? '0'),
                ];
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

    private function normalizeName(string $name): string
    {
        $name = trim(html_entity_decode($name, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $name = (string) preg_replace('/\s+/u', ' ', $name);
        if (mb_strlen($name) > 180) {
            $name = mb_substr($name, 0, 180);
        }

        return $name;
    }
}
