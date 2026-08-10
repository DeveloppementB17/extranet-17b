<?php

declare(strict_types=1);

namespace App\Entreprise;

/**
 * Résultat d’un import d’entreprises legacy.
 */
final class LegacyEntrepriseImportResult
{
    /**
     * @param list<string> $skippedReasons
     */
    public function __construct(
        public readonly int $created = 0,
        public readonly int $updated = 0,
        public readonly int $skipped = 0,
        public readonly array $skippedReasons = [],
    ) {
    }
}
