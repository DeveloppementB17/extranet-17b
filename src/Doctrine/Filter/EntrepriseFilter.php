<?php

namespace App\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Filtre multi-tenant appliqué automatiquement.
 *
 * Stratégie: toute entité qui possède une association ManyToOne nommée "entreprise"
 * est automatiquement filtrée par entreprise_id.
 */
final class EntrepriseFilter extends SQLFilter
{
    public const PARAM_NAME = 'entreprise_id';

    public function addFilterConstraint(ClassMetadata $targetEntity, $targetTableAlias): string
    {
        if (!$targetEntity->hasAssociation('entreprise')) {
            return '';
        }

        $mapping = $targetEntity->getAssociationMapping('entreprise');
        if (($mapping['type'] ?? null) !== ClassMetadata::MANY_TO_ONE) {
            return '';
        }

        // Si le paramètre n'est pas défini, on refuse d'appliquer un filtre "ouvert"
        // (le subscriber désactive le filtre quand on n'a pas de contexte entreprise).
        try {
            $entrepriseId = $this->getParameter(self::PARAM_NAME);
        } catch (\InvalidArgumentException) {
            return '';
        }

        $column = $mapping['joinColumns'][0]['name'] ?? 'entreprise_id';

        return sprintf('%s.%s = %s', $targetTableAlias, $column, $entrepriseId);
    }
}

