<?php

namespace App\Security\Voter;

use App\Entity\TimeCredit;
use App\Entity\User;
use App\Tenant\ManagedClientContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TimeCreditVoter extends Voter
{
    public const VIEW = 'TIME_CREDIT_VIEW';
    public const MANAGE = 'TIME_CREDIT_MANAGE';
    public const INTERVENE = 'TIME_CREDIT_INTERVENE';

    public function __construct(
        private readonly ManagedClientContext $managedClientContext,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!$subject instanceof TimeCredit) {
            return false;
        }

        return \in_array($attribute, [self::VIEW, self::MANAGE, self::INTERVENE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var TimeCredit $credit */
        $credit = $subject;
        $creditEntreprise = $credit->getEntreprise();
        if ($creditEntreprise === null || $creditEntreprise->getId() === null || $creditEntreprise->isAgency()) {
            return false;
        }

        if ($user->is17bAdmin()) {
            return true;
        }

        if ($user->is17bUser()) {
            $selected = $this->managedClientContext->getSelectedManagedEntreprise($user);
            if ($selected === null || $selected->getId() !== $creditEntreprise->getId()) {
                return false;
            }

            return $attribute !== self::MANAGE || $user->managesEntreprise($creditEntreprise);
        }

        if ($user->isCustomerActor()) {
            $entreprise = $user->getEntreprise();
            if ($entreprise === null || $entreprise->isAgency() || $entreprise->getId() !== $creditEntreprise->getId()) {
                return false;
            }

            return $attribute === self::VIEW;
        }

        return false;
    }
}
