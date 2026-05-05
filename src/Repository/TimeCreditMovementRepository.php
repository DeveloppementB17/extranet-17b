<?php

namespace App\Repository;

use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeCreditMovement>
 */
class TimeCreditMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeCreditMovement::class);
    }

    /**
     * @return list<TimeCreditMovement>
     */
    public function findByCreditOrdered(TimeCredit $credit): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.createdBy', 'u')
            ->addSelect('u')
            ->andWhere('m.timeCredit = :tc')
            ->setParameter('tc', $credit)
            ->orderBy('m.occurredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
