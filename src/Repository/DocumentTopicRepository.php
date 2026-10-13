<?php

namespace App\Repository;

use App\Entity\DocumentTopic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentTopic>
 */
class DocumentTopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentTopic::class);
    }

    /**
     * @return list<DocumentTopic>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->addSelect("CASE WHEN LOWER(t.name) = 'autre' THEN 1 ELSE 0 END AS HIDDEN autre_sort")
            ->orderBy('autre_sort', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
