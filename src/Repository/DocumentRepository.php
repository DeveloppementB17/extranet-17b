<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * @return list<Document>
     */
    public function findAccessibleForUser(User $user, ?Entreprise $forcedEntreprise = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC');

        if ($user->is17bAdmin()) {
            $qb->join('d.entreprise', 'e')
                ->andWhere('e.agency = :fa')
                ->setParameter('fa', false);
            if ($forcedEntreprise instanceof Entreprise) {
                $qb->andWhere('d.entreprise = :forcedEntreprise')
                    ->setParameter('forcedEntreprise', $forcedEntreprise);
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
     * @return list<array{id:int,name:string,documentsCount:int}>
     */
    public function findCategorySummariesByEntreprise(Entreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('c.id AS id, c.name AS name, COUNT(d.id) AS documentsCount')
            ->join('d.category', 'c')
            ->andWhere('d.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
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
}
