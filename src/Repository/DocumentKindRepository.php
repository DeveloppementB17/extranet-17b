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
            ->orderBy('k.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
