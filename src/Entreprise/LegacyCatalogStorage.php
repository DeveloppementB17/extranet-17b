<?php

declare(strict_types=1);

namespace App\Entreprise;

/**
 * Stockage local des catalogues JSON « anciennes données ».
 */
final class LegacyCatalogStorage
{
    public const string ENTREPRISES_FILENAME = 'entreprises.json';
    public const string TIME_CREDITS_FILENAME = 'time-credits.json';

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function directory(): string
    {
        return $this->projectDir.'/var/legacy';
    }

    public function entreprisesPath(): string
    {
        return $this->directory().'/'.self::ENTREPRISES_FILENAME;
    }

    public function timeCreditsPath(): string
    {
        return $this->directory().'/'.self::TIME_CREDITS_FILENAME;
    }

    public function hasEntreprisesCatalog(): bool
    {
        return is_file($this->entreprisesPath()) && is_readable($this->entreprisesPath());
    }

    public function hasTimeCreditsCatalog(): bool
    {
        return is_file($this->timeCreditsPath()) && is_readable($this->timeCreditsPath());
    }

    public function ensureDirectory(): void
    {
        $dir = $this->directory();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Impossible de créer %s', $dir));
        }
    }

    public function storeEntreprisesJson(string $json): void
    {
        $this->ensureDirectory();
        if (file_put_contents($this->entreprisesPath(), $json) === false) {
            throw new \RuntimeException('Écriture du catalogue entreprises impossible.');
        }
    }

    public function storeTimeCreditsJson(string $json): void
    {
        $this->ensureDirectory();
        if (file_put_contents($this->timeCreditsPath(), $json) === false) {
            throw new \RuntimeException('Écriture du catalogue crédits temps impossible.');
        }
    }

    public function readEntreprisesJson(): string
    {
        if (!$this->hasEntreprisesCatalog()) {
            throw new \RuntimeException('Catalogue entreprises absent. Chargez le fichier JSON.');
        }

        return (string) file_get_contents($this->entreprisesPath());
    }

    public function readTimeCreditsJson(): string
    {
        if (!$this->hasTimeCreditsCatalog()) {
            throw new \RuntimeException('Catalogue crédits temps absent. Chargez le fichier JSON.');
        }

        return (string) file_get_contents($this->timeCreditsPath());
    }
}
