<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Tenant\EntrepriseOwnedInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter "socle" pour les entités rattachées à une entreprise.
 *
 * Convention: toute entité métier implémente EntrepriseOwnedInterface.
 * On refuse l'accès si l'objet ne correspond pas à l'entreprise de l'utilisateur.
 */
final class EntrepriseOwnedVoter extends Voter
{
    public const VIEW = 'ENTREPRISE_OWNED_VIEW';
    public const EDIT = 'ENTREPRISE_OWNED_EDIT';
    public const DELETE = 'ENTREPRISE_OWNED_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)) {
            return false;
        }

        return $subject instanceof EntrepriseOwnedInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $entreprise = $user->getEntreprise();
        if ($entreprise === null || $entreprise->getId() === null) {
            return false;
        }

        /** @var EntrepriseOwnedInterface $subject */
        $subjectEntreprise = $subject->getEntreprise();
        if ($subjectEntreprise === null || $subjectEntreprise->getId() === null) {
            return false;
        }

        return $subjectEntreprise->getId() === $entreprise->getId();
    }
}

