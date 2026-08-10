<?php

namespace App\Repository;

use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Service\Monitor\SiteUrlMatcher;
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

    public function findInitialAllocation(TimeCredit $credit): ?TimeCreditMovement
    {
        $movements = $this->createQueryBuilder('m')
            ->andWhere('m.timeCredit = :tc')
            ->andWhere('m.type = :type')
            ->setParameter('tc', $credit)
            ->setParameter('type', TimeCreditMovement::TYPE_ALLOCATION)
            ->orderBy('m.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $movements[0] ?? null;
    }

    public function countInterventions(TimeCredit $credit): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.timeCredit = :tc')
            ->andWhere('m.type = :type')
            ->setParameter('tc', $credit)
            ->setParameter('type', TimeCreditMovement::TYPE_INTERVENTION)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<TimeCreditMovement>
     */
    public function findByCreditChronological(TimeCredit $credit): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.timeCredit = :tc')
            ->setParameter('tc', $credit)
            ->orderBy('m.occurredAt', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAllocations(TimeCredit $credit): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.timeCredit = :tc')
            ->andWhere('m.type = :type')
            ->setParameter('tc', $credit)
            ->setParameter('type', TimeCreditMovement::TYPE_ALLOCATION)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Interventions pour le monitor 17B (filtre entreprise Doctrine désactivé côté appelant).
     *
     * @return list<array{
     *   movement: TimeCreditMovement,
     *   match_reason: string
     * }>
     */
    public function findInterventionsForMonitor(
        SiteUrlMatcher $matcher,
        ?int $timeCreditId,
        ?int $entrepriseId,
        ?string $siteUrl,
        int $limit = 15,
    ): array {
        if ($timeCreditId !== null) {
            $rows = $this->createQueryBuilder('m')
                ->leftJoin('m.createdBy', 'u')
                ->addSelect('u')
                ->innerJoin('m.timeCredit', 'tc')
                ->addSelect('tc')
                ->innerJoin('tc.entreprise', 'e')
                ->addSelect('e')
                ->andWhere('m.type = :type')
                ->andWhere('tc.id = :tcId')
                ->setParameter('type', TimeCreditMovement::TYPE_INTERVENTION)
                ->setParameter('tcId', $timeCreditId)
                ->orderBy('m.occurredAt', 'DESC')
                ->addOrderBy('m.id', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return array_map(
                static fn (TimeCreditMovement $movement): array => [
                    'movement' => $movement,
                    'match_reason' => 'time_credit_id',
                ],
                $rows,
            );
        }

        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.createdBy', 'u')
            ->addSelect('u')
            ->innerJoin('m.timeCredit', 'tc')
            ->addSelect('tc')
            ->innerJoin('tc.entreprise', 'e')
            ->addSelect('e')
            ->andWhere('m.type = :type')
            ->setParameter('type', TimeCreditMovement::TYPE_INTERVENTION)
            ->orderBy('m.occurredAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(200);

        if ($entrepriseId !== null) {
            $qb->andWhere('e.id = :entrepriseId')
                ->setParameter('entrepriseId', $entrepriseId);
        }

        $candidates = $qb->getQuery()->getResult();
        if ($siteUrl === null || trim($siteUrl) === '') {
            return array_slice(
                array_map(
                    static fn (TimeCreditMovement $movement): array => [
                        'movement' => $movement,
                        'match_reason' => 'entreprise_id',
                    ],
                    $candidates,
                ),
                0,
                $limit,
            );
        }

        $matched = [];
        foreach ($candidates as $movement) {
            if (!$movement instanceof TimeCreditMovement) {
                continue;
            }

            $credit = $movement->getTimeCredit();
            $reason = null;

            if ($credit !== null && $matcher->hostsMatch($credit->getSiteUrl(), $siteUrl)) {
                $reason = 'site_url';
            } elseif ($matcher->textContainsHost($movement->getDescription(), $siteUrl)) {
                $reason = 'description';
            } elseif ($entrepriseId !== null) {
                $reason = 'entreprise_id';
            }

            if ($reason === null) {
                continue;
            }

            $matched[] = [
                'movement' => $movement,
                'match_reason' => $reason,
            ];

            if (count($matched) >= $limit) {
                break;
            }
        }

        return $matched;
    }
}
