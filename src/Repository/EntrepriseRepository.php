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

    /**
     * Entreprises clientes sélectionnables dans le switcher staff 17b.
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
    public function findNonAgencyOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.agency = :fa')
            ->setParameter('fa', false)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
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
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('e')
            ->orderBy('e.agency', 'DESC')
            ->addOrderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Entreprise>
     */
    public function findNonAgencyByIdsOrdered(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('e')
            ->andWhere('e.agency = :fa')
            ->andWhere('e.id IN (:ids)')
            ->setParameter('fa', false)
            ->setParameter('ids', $ids)
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

