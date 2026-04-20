<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Comptes clients (CUSTOMER_*) pour l’attribution des documents par l’équipe 17b.
     *
     * @return list<User>
     */
    public function findCustomerAccountsForStaffUpload(User $actor): array
    {
        if (!$actor->is17bStaff()) {
            return [];
        }

        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.entreprise', 'e')
            ->andWhere('e.agency = :fa')
            ->setParameter('fa', false)
            ->orderBy('e.name', 'ASC')
            ->addOrderBy('u.email', 'ASC');

        if ($actor->is17bUser()) {
            $ids = $actor->getManagedEntrepriseIds();
            if ($ids === []) {
                return [];
            }
            $qb->andWhere('e.id IN (:mids)')
                ->setParameter('mids', $ids);
        }

        $users = $qb->getQuery()->getResult();

        return array_values(array_filter(
            $users,
            static fn (User $u): bool => $u->isCustomerActor(),
        ));
    }

    /**
     * @return list<User>
     */
    public function findAllForAdminOrdered(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.entreprise', 'e')
            ->orderBy('e.name', 'ASC')
            ->addOrderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<User>
     */
    public function findCustomerUsersForEntreprise(int $entrepriseId): array
    {
        $users = $this->createQueryBuilder('u')
            ->innerJoin('u.entreprise', 'e')
            ->andWhere('e.id = :eid')
            ->setParameter('eid', $entrepriseId)
            ->orderBy('u.email', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $users,
            static fn (User $u): bool => $u->isCustomerUser(),
        ));
    }
}
