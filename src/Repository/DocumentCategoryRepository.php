<?php

namespace App\Repository;

use App\Entity\DocumentCategory;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentCategory>
 */
class DocumentCategoryRepository extends ServiceEntityRepository
{
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
     * @return list<DocumentCategory>
     */
    public function findRootsFor17bStaff(User $actor): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.entreprise', 'e')
            ->andWhere('c.parent IS NULL')
            ->andWhere('e.agency = :fa')
            ->setParameter('fa', false)
            ->orderBy('e.name', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($actor->is17bUser() && !$actor->is17bAdmin()) {
            $ids = $actor->getManagedEntrepriseIds();
            if ($ids === []) {
                return [];
            }
            $qb->andWhere('e.id IN (:ids)')
                ->setParameter('ids', $ids);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<DocumentCategory>
     */
    public function findAllInEntrepriseOrdered(int $entrepriseId): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.entreprise', 'e')
            ->andWhere('e.id = :eid')
            ->setParameter('eid', $entrepriseId)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<DocumentCategory>
     */
    public function findRootsForEntreprise(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parent IS NULL')
            ->andWhere('c.entreprise = :e')
            ->setParameter('e', $entreprise)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

