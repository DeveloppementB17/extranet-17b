<?php

namespace App\Security\Voter;

use App\Entity\Document;
use App\Entity\User;
use App\Tenant\ManagedClientContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lecture / téléchargement des documents (périmètre entreprise cliente + rôles).
 */
final class DocumentVoter extends Voter
{
    public const VIEW = 'DOCUMENT_VIEW';

    public const DOWNLOAD = 'DOCUMENT_DOWNLOAD';

    public function __construct(
        private readonly ManagedClientContext $managedClientContext,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Document
            && \in_array($attribute, [self::VIEW, self::DOWNLOAD], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Document $document */
        $document = $subject;
        $docEntreprise = $document->getEntreprise();

        if ($docEntreprise === null || $docEntreprise->getId() === null || $docEntreprise->isAgency()) {
            return false;
        }

        if ($user->is17bAdmin()) {
            return true;
        }

        if ($user->is17bUser()) {
            $selectedEntreprise = $this->managedClientContext->getSelectedManagedEntreprise($user);

            return $selectedEntreprise !== null
                && $selectedEntreprise->getId() === $docEntreprise->getId();
        }

        if ($user->isCustomerActor()) {
            $entreprise = $user->getEntreprise();

            return $entreprise !== null
                && !$entreprise->isAgency()
                && $entreprise->getId() === $docEntreprise->getId();
        }

        return false;
    }
}
