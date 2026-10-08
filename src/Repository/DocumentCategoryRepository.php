<?php

namespace App\Repository;

use App\Entity\DocumentCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentCategory>
 */
class DocumentCategoryRepository extends ServiceEntityRepository
{
    public const STRATEGY_ROOT_NAME = 'Stratégie de communication';
    public const STRATEGY_FOLDER_NAMES = ['Stratégie', 'Pilotage'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentCategory::class);
    }

    /**
     * @return list<DocumentCategory>
     */
    public function findRoots(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Racines hors module Stratégie (arborescence Fichiers).
     *
     * @return list<DocumentCategory>
     */
    public function findNonStrategyRoots(): array
    {
        return array_values(array_filter(
            $this->findRoots(),
            static fn (DocumentCategory $c): bool => $c->getName() !== self::STRATEGY_ROOT_NAME,
        ));
    }

    /**
     * @return list<DocumentCategory>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findStrategyRoot(): ?DocumentCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.name = :name')
            ->setParameter('name', self::STRATEGY_ROOT_NAME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dossiers Stratégie et Pilotage (enfants de la racine Stratégie de communication).
     *
     * @return list<DocumentCategory>
     */
    public function findStrategyFolders(): array
    {
        $root = $this->findStrategyRoot();
        if (!$root instanceof DocumentCategory) {
            return [];
        }

        $folders = [];
        foreach ($root->getChildren() as $child) {
            if (\in_array($child->getName(), self::STRATEGY_FOLDER_NAMES, true)) {
                $folders[] = $child;
            }
        }

        usort($folders, static fn (DocumentCategory $a, DocumentCategory $b): int => strcasecmp($a->getName(), $b->getName()));

        return $folders;
    }

    /**
     * IDs du sous-arbre « Stratégie de communication » (racine incluse).
     *
     * @return list<int>
     */
    public function findStrategySubtreeIds(): array
    {
        $root = $this->findStrategyRoot();
        if (!$root instanceof DocumentCategory || $root->getId() === null) {
            return [];
        }

        return $this->collectSubtreeIds($root);
    }

    /**
     * @return list<int>
     */
    public function findStrategyFolderIds(): array
    {
        $ids = [];
        foreach ($this->findStrategyFolders() as $folder) {
            $id = $folder->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Choix de formulaire : uniquement Stratégie et Pilotage.
     *
     * @return array<string, DocumentCategory>
     */
    public function buildStrategyCategoryChoices(): array
    {
        $choices = [];
        foreach ($this->findStrategyFolders() as $folder) {
            $choices[$folder->getName()] = $folder;
        }

        return $choices;
    }

    /**
     * @return list<int>
     */
    private function collectSubtreeIds(DocumentCategory $root): array
    {
        $ids = [];
        $id = $root->getId();
        if ($id !== null) {
            $ids[] = $id;
        }
        foreach ($root->getChildren() as $child) {
            $ids = array_merge($ids, $this->collectSubtreeIds($child));
        }

        return $ids;
    }
}
