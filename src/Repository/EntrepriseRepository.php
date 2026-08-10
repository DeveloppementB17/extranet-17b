<?php

namespace App\Repository;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entreprise>
 */
class EntrepriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entreprise::class);
    }

    public function findOneBySlug(string $slug): ?Entreprise
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByLegacySourceId(int $legacySourceId): ?Entreprise
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.legacySourceId = :legacySourceId')
            ->setParameter('legacySourceId', $legacySourceId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function existsByName(string $name, ?int $excludeEntrepriseId = null): bool
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return false;
        }

        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('LOWER(e.name) = :name')
            ->setParameter('name', $normalized);

        if ($excludeEntrepriseId !== null) {
            $qb->andWhere('e.id != :excludeId')
                ->setParameter('excludeId', $excludeEntrepriseId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Entreprises clientes sélectionnables dans le switcher staff 17b (hors legacy).
     *
     * @return list<Entreprise>
     */
    public function findSwitchableClientsForStaff(User $user): array
    {
        if ($user->is17bAdmin()) {
            return $this->findNonAgencyOrdered();
        }

        return $this->findNonAgencyByIdsOrdered($user->getManagedEntrepriseIds());
    }

    /**
     * @return list<Entreprise>
     */
    public function findNonAgencyOrdered(bool $includeLegacy = false): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.agency = :fa')
            ->setParameter('fa', false)
            ->orderBy('e.name', 'ASC');

        if (!$includeLegacy) {
            $qb->andWhere('e.legacy = :legacy')
                ->setParameter('legacy', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<Entreprise>
     */
    public function findAgenciesOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.agency = :t')
            ->setParameter('t', true)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Entreprise>
     */
    public function findAllOrdered(bool $includeLegacy = true): array
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.agency', 'DESC')
            ->addOrderBy('e.name', 'ASC');

        if (!$includeLegacy) {
            $qb->andWhere('e.legacy = :legacy')
                ->setParameter('legacy', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Entreprise>
     */
    public function findNonAgencyByIdsOrdered(array $ids, bool $includeLegacy = false): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.agency = :fa')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('fa', false)
            ->setParameter('ids', $ids)
            ->orderBy('e.name', 'ASC');

        if (!$includeLegacy) {
            $qb->andWhere('e.legacy = :legacy')
                ->setParameter('legacy', false);
        }

        return $qb->getQuery()->getResult();
    }

    public function countLegacy(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.legacy = :legacy')
            ->setParameter('legacy', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
