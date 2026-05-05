<?php

namespace App\Repository;

use App\Entity\Entreprise;
use App\Entity\TimeCredit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TimeCredit>
 */
class TimeCreditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeCredit::class);
    }

    /**
     * @return list<TimeCredit>
     */
    public function findAccessibleForUser(User $user, ?Entreprise $forcedEntreprise = null): array
    {
        $qb = $this->createQueryBuilder('tc')
            ->join('tc.entreprise', 'e')
            ->leftJoin('tc.category', 'cat')
            ->addSelect('e', 'cat')
            ->andWhere('e.agency = :fa')
            ->setParameter('fa', false)
            ->orderBy('tc.archived', 'ASC')
            ->addOrderBy('tc.createdAt', 'DESC');

        if ($user->is17bAdmin()) {
            if ($forcedEntreprise !== null) {
                $qb->andWhere('tc.entreprise = :forced')
                    ->setParameter('forced', $forcedEntreprise);
            }

            return $qb->getQuery()->getResult();
        }

        if ($user->is17bUser()) {
            if ($forcedEntreprise !== null) {
                if (!$user->managesEntreprise($forcedEntreprise)) {
                    return [];
                }
                $qb->andWhere('tc.entreprise = :forced')
                    ->setParameter('forced', $forcedEntreprise);

                return $qb->getQuery()->getResult();
            }

            $ids = $user->getManagedEntrepriseIds();
            if ($ids === []) {
                return [];
            }
            $qb->andWhere('tc.entreprise IN (:ids)')
                ->setParameter('ids', $ids);

            return $qb->getQuery()->getResult();
        }

        if ($user->isCustomerActor()) {
            $entreprise = $user->getEntreprise();
            $eid = $entreprise?->getId();
            if ($eid === null || $entreprise->isAgency()) {
                return [];
            }

            $qb->andWhere('tc.entreprise = :e')
                ->setParameter('e', $entreprise);

            return $qb->getQuery()->getResult();
        }

        return [];
    }

    /**
     * @return list<TimeCredit>
     */
    public function findActiveByEntreprise(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('tc')
            ->leftJoin('tc.category', 'cat')
            ->addSelect('cat')
            ->andWhere('tc.entreprise = :e')
            ->andWhere('tc.archived = :archived')
            ->andWhere('tc.remainingMinutes > 0')
            ->setParameter('e', $entreprise)
            ->setParameter('archived', false)
            ->orderBy('tc.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
