<?php

declare(strict_types=1);

namespace App\Recette;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Charge la définition statique des points de recette (config/recette/checklist-definition.json).
 */
final class RecetteChecklistDefinition
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/recette/checklist-definition.json')]
        private readonly string $definitionPath,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinition(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!is_readable($this->definitionPath)) {
            throw new \RuntimeException(sprintf('Définition de recette introuvable : %s', $this->definitionPath));
        }

        $content = file_get_contents($this->definitionPath);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Impossible de lire : %s', $this->definitionPath));
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Définition de recette JSON invalide.');
        }

        return $this->cache = $data;
    }

    /**
     * @return list<array{id: string, label: string, hint?: string}>
     */
    public function getRoles(): array
    {
        $roles = $this->getDefinition()['roles'] ?? [];

        return is_array($roles) ? array_values($roles) : [];
    }

    /**
     * @return list<array{id: string, label: string, default?: bool}>
     */
    public function getStatuses(): array
    {
        $statuses = $this->getDefinition()['statuses'] ?? [];

        return is_array($statuses) ? array_values($statuses) : [];
    }

    public function getDefaultStatusId(): string
    {
        foreach ($this->getStatuses() as $status) {
            if (!empty($status['default'])) {
                return (string) $status['id'];
            }
        }

        return 'non_verifie';
    }

    /**
     * Sections et items applicables au rôle de recette choisi.
     *
     * @return list<array<string, mixed>>
     */
    public function getSectionsForRole(string $roleId): array
    {
        $sections = $this->getDefinition()['sections'] ?? [];
        if (!is_array($sections)) {
            return [];
        }

        $filtered = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $sectionRoles = $section['roles'] ?? [];
            if (!is_array($sectionRoles) || !in_array($roleId, $sectionRoles, true)) {
                continue;
            }

            $items = [];
            foreach ($section['items'] ?? [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemRoles = $item['roles'] ?? $sectionRoles;
                if (!is_array($itemRoles) || !in_array($roleId, $itemRoles, true)) {
                    continue;
                }

                $items[] = $item;
            }

            if ($items === []) {
                continue;
            }

            $section['items'] = $items;
            $filtered[] = $section;
        }

        return $filtered;
    }

    /**
     * @return list<string>
     */
    public function getItemIdsForRole(string $roleId): array
    {
        $ids = [];
        foreach ($this->getSectionsForRole($roleId) as $section) {
            foreach ($section['items'] as $item) {
                $ids[] = (string) $item['id'];
            }
        }

        return $ids;
    }

    public function isValidStatus(string $statusId): bool
    {
        foreach ($this->getStatuses() as $status) {
            if (($status['id'] ?? '') === $statusId) {
                return true;
            }
        }

        return false;
    }

    public function isValidRole(string $roleId): bool
    {
        foreach ($this->getRoles() as $role) {
            if (($role['id'] ?? '') === $roleId) {
                return true;
            }
        }

        return false;
    }
}
