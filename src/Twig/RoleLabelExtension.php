<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class RoleLabelExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('role_label', $this->roleLabel(...)),
        ];
    }

    public function roleLabel(string $role): string
    {
        /** @var array<string, string> $map */
        $map = [
            'ROLE_USER' => 'Utilisateur',
            'ROLE_CUSTOMER' => 'Espace client',
            'ROLE_CUSTOMER_USER' => 'Utilisateur client',
            'ROLE_CUSTOMER_ADMIN' => 'Administrateur client',
            'ROLE_17B_USER' => 'Utilisateur 17b',
            'ROLE_17B_ADMIN' => 'Administrateur 17b',
        ];

        return $map[$role] ?? $role;
    }
}
