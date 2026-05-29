<?php

namespace App\Security\Voter;

use App\Entity\TimeCreditMovement;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Modification et suppression d'une intervention : admin 17b ou auteur de l'intervention.
 */
final class TimeCreditMovementVoter extends Voter
{
    public const MANAGE_INTERVENTION = 'TIME_CREDIT_INTERVENTION_MANAGE';

    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof TimeCreditMovement
            && $attribute === self::MANAGE_INTERVENTION;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->is17bStaff()) {
            return false;
        }

        /** @var TimeCreditMovement $movement */
        $movement = $subject;
        if ($movement->getType() !== TimeCreditMovement::TYPE_INTERVENTION) {
            return false;
        }

        $credit = $movement->getTimeCredit();
        if ($credit === null || !$this->authorizationChecker->isGranted(TimeCreditVoter::INTERVENE, $credit)) {
            return false;
        }

        if ($user->is17bAdmin()) {
            return true;
        }

        $creator = $movement->getCreatedBy();

        return $creator !== null && $creator->getId() === $user->getId();
    }
}
