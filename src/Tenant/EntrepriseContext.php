<?php

namespace App\Tenant;

use App\Entity\Entreprise;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Source unique de vérité pour l'entreprise "courante".
 *
 * Pour l'instant, on la déduit de l'utilisateur connecté.
 * Plus tard, on pourra l'étendre (sous-domaine, sélection, etc.).
 */
final class EntrepriseContext
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    public function getCurrentEntreprise(): ?Entreprise
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $user->getEntreprise();
    }
}

