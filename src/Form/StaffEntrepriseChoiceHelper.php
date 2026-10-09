<?php

namespace App\Form;

use App\Entity\Entreprise;
use App\Entity\User;

/**
 * Groupe les entreprises comme le sélecteur sidebar (Mes clients / Autres).
 */
final class StaffEntrepriseChoiceHelper
{
    /**
     * @param list<Entreprise> $entreprises
     *
     * @return list<Entreprise>|array<string, list<Entreprise>>
     */
    public static function groupForStaff(User $user, array $entreprises): array
    {
        if (!$user->is17bAdmin() || $entreprises === []) {
            return $entreprises;
        }

        $preferredIds = array_fill_keys($user->getManagedEntrepriseIds(), true);
        if ($preferredIds === []) {
            return $entreprises;
        }

        $preferred = [];
        $others = [];
        foreach ($entreprises as $entreprise) {
            $id = $entreprise->getId();
            if ($id !== null && isset($preferredIds[$id])) {
                $preferred[] = $entreprise;
            } else {
                $others[] = $entreprise;
            }
        }

        if ($preferred === []) {
            return $entreprises;
        }

        $choices = ['Mes clients' => $preferred];
        if ($others !== []) {
            $choices['Autres clients'] = $others;
        }

        return $choices;
    }
}
