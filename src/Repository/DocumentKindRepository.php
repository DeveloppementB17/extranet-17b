<?php

namespace App\Repository;

use App\Entity\DocumentKind;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentKind>
 */
class DocumentKindRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentKind::class);
    }

    /**
     * @return list<DocumentKind>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('k')
            ->addSelect("CASE WHEN LOWER(k.name) = 'autre' THEN 1 ELSE 0 END AS HIDDEN autre_sort")
            ->orderBy('autre_sort', 'ASC')
            ->addOrderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
