<?php

namespace App\Tenant;

use App\Entity\Entreprise;

/**
 * Marqueur pour les entités "métier" rattachées à une Entreprise (multi-tenant).
 *
 * Règle projet: ne jamais accéder à une entité métier sans filtrer par entreprise.
 */
interface EntrepriseOwnedInterface
{
    public function getEntreprise(): ?Entreprise;
}

