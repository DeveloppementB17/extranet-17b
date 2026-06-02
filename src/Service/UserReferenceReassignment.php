<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\TimeCredit;
use App\Entity\TimeCreditMovement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Réattribution des enregistrements liés à un utilisateur avant suppression.
 */
final class UserReferenceReassignment
{
    public function countDocumentReferences(User $user, EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.client = :u OR d.uploadedBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUploadedDocuments(User $user, EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(d.id)')
            ->from(Document::class, 'd')
            ->where('d.uploadedBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTimeCreditReferences(User $user, EntityManagerInterface $entityManager): int
    {
        $credits = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(TimeCredit::class, 't')
            ->where('t.createdBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $movements = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(TimeCreditMovement::class, 'm')
            ->where('m.createdBy = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $credits + $movements;
    }

    public function requiresSuccessor(User $user, EntityManagerInterface $entityManager): bool
    {
        return $this->countDocumentReferences($user, $entityManager) > 0
            || $this->countTimeCreditReferences($user, $entityManager) > 0;
    }

    public function reassign(User $from, User $to, EntityManagerInterface $entityManager): void
    {
        if ($from->getId() === $to->getId()) {
            throw new \InvalidArgumentException('Le compte successeur doit être différent de l’utilisateur supprimé.');
        }

        $entityManager->createQueryBuilder()
            ->update(Document::class, 'd')
            ->set('d.uploadedBy', ':to')
            ->where('d.uploadedBy = :from')
            ->setParameter('to', $to)
            ->setParameter('from', $from)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->update(Document::class, 'd')
            ->set('d.client', ':to')
            ->where('d.client = :from')
            ->setParameter('to', $to)
            ->setParameter('from', $from)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->update(TimeCredit::class, 't')
            ->set('t.createdBy', ':to')
            ->where('t.createdBy = :from')
            ->setParameter('to', $to)
            ->setParameter('from', $from)
            ->getQuery()
            ->execute();

        $entityManager->createQueryBuilder()
            ->update(TimeCreditMovement::class, 'm')
            ->set('m.createdBy', ':to')
            ->where('m.createdBy = :from')
            ->setParameter('to', $to)
            ->setParameter('from', $from)
            ->getQuery()
            ->execute();
    }
}
