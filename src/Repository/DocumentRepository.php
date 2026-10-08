<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly DocumentCategoryRepository $documentCategoryRepository,
    ) {
        parent::__construct($registry, Document::class);
    }

    /**
     * @param list<int>|null $forcedEntrepriseIds Filtre multi-entreprises (ex. scope « mes clients » admin)
     * @param 'fichiers'|'strategie'|'all' $area
     *
     * @return list<Document>
     */
    public function findAccessibleForUser(
        User $user,
        ?Entreprise $forcedEntreprise = null,
        ?array $forcedEntrepriseIds = null,
        string $area = 'fichiers',
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.documentDate', 'DESC')
            ->addOrderBy('d.createdAt', 'DESC');

        $this->applyAreaFilter($qb, $area);

        if ($user->is17bAdmin()) {
            $qb->join('d.entreprise', 'e')
                ->andWhere('e.agency = :fa')
                ->setParameter('fa', false);
            if ($forcedEntreprise instanceof Entreprise) {
                $qb->andWhere('d.entreprise = :forcedEntreprise')
                    ->setParameter('forcedEntreprise', $forcedEntreprise);
            } elseif ($forcedEntrepriseIds !== null) {
                if ($forcedEntrepriseIds === []) {
                    return [];
                }
                $qb->andWhere('d.entreprise IN (:forcedIds)')
                    ->setParameter('forcedIds', $forcedEntrepriseIds);
            }

            return $qb->getQuery()->getResult();
        }

        if ($user->is17bUser()) {
            if ($forcedEntreprise instanceof Entreprise) {
                if (!$user->managesEntreprise($forcedEntreprise)) {
                    return [];
                }

                $qb->andWhere('d.entreprise = :forcedEntreprise')
                    ->setParameter('forcedEntreprise', $forcedEntreprise);

                return $qb->getQuery()->getResult();
            }

            $ids = $user->getManagedEntrepriseIds();
            if ($ids === []) {
                return [];
            }

            $qb->andWhere('d.entreprise IN (:ids)')
                ->setParameter('ids', $ids);

            return $qb->getQuery()->getResult();
        }

        if ($user->isCustomerActor()) {
            $entreprise = $user->getEntreprise();
            $eid = $entreprise?->getId();
            if ($eid === null) {
                return [];
            }

            $qb->andWhere('d.entreprise = :e')
                ->setParameter('e', $entreprise);

            return $qb->getQuery()->getResult();
        }

        return [];
    }

    public function countByCategory(DocumentCategory $category): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.category = :c')
            ->setParameter('c', $category)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return array<int, int>
     */
    public function countByCategoryIds(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('IDENTITY(d.category) AS categoryId, COUNT(d.id) AS documentsCount')
            ->andWhere('d.category IN (:ids)')
            ->setParameter('ids', $categoryIds)
            ->groupBy('d.category')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $categoryId = (int) ($row['categoryId'] ?? 0);
            if ($categoryId > 0) {
                $counts[$categoryId] = (int) ($row['documentsCount'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * Résumés dossiers Fichiers (hors sous-arbre Stratégie).
     *
     * @return list<array{id:int,name:string,documentsCount:int}>
     */
    public function findCategorySummariesByEntreprise(Entreprise $entreprise): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('c.id AS id, c.name AS name, COUNT(d.id) AS documentsCount')
            ->join('d.category', 'c')
            ->andWhere('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'ASC');

        $this->applyAreaFilter($qb, 'fichiers');

        $rows = $qb->getQuery()->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'documentsCount' => (int) $row['documentsCount'],
            ],
            $rows,
        );
    }

    /**
     * Résumés dossiers Stratégie / Pilotage uniquement.
     *
     * @return list<array{id:int,name:string,documentsCount:int}>
     */
    public function findStrategyFolderSummariesByEntreprise(Entreprise $entreprise): array
    {
        $folderIds = $this->documentCategoryRepository->findStrategyFolderIds();
        if ($folderIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('d')
            ->select('c.id AS id, c.name AS name, COUNT(d.id) AS documentsCount')
            ->join('d.category', 'c')
            ->andWhere('d.entreprise = :entreprise')
            ->andWhere('d.category IN (:folderIds)')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('folderIds', $folderIds)
            ->groupBy('c.id, c.name')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'documentsCount' => (int) $row['documentsCount'],
            ],
            $rows,
        );
    }

    public function isStrategyDocument(Document $document): bool
    {
        $category = $document->getCategory();
        if (!$category instanceof DocumentCategory || $category->getId() === null) {
            return false;
        }

        return \in_array($category->getId(), $this->documentCategoryRepository->findStrategySubtreeIds(), true);
    }

    /**
     * @param 'fichiers'|'strategie'|'all' $area
     */
    private function applyAreaFilter(QueryBuilder $qb, string $area): void
    {
        if ($area === 'all') {
            return;
        }

        $strategyIds = $this->documentCategoryRepository->findStrategySubtreeIds();
        if ($strategyIds === []) {
            if ($area === 'strategie') {
                $qb->andWhere('1 = 0');
            }

            return;
        }

        if ($area === 'fichiers') {
            $qb->andWhere('d.category IS NULL OR d.category NOT IN (:strategyCategoryIds)')
                ->setParameter('strategyCategoryIds', $strategyIds);

            return;
        }

        if ($area === 'strategie') {
            $folderIds = $this->documentCategoryRepository->findStrategyFolderIds();
            if ($folderIds === []) {
                $qb->andWhere('1 = 0');

                return;
            }
            $qb->andWhere('d.category IN (:strategyFolderIds)')
                ->setParameter('strategyFolderIds', $folderIds);
        }
    }
}
