<?php

namespace App\Repository;

use App\Entity\Document;
use App\Entity\DocumentCategory;
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
    public function findAccessibleForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.createdAt', 'DESC');

        if ($user->is17bAdmin()) {
            $qb->join('d.entreprise', 'e')
                ->andWhere('e.agency = :fa')
                ->setParameter('fa', false);

            return $qb->getQuery()->getResult();
        }

        if ($user->is17bUser()) {
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
}
