<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Tenant\ManagedClientContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ManagedClientExtension extends AbstractExtension
{
    public function __construct(
        private readonly ManagedClientContext $managedClientContext,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('managed_selected_client', $this->getManagedSelectedClient(...)),
        ];
    }

    public function getManagedSelectedClient(): ?Entreprise
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->is17bStaff()) {
            return null;
        }

        return $this->managedClientContext->getSelectedManagedEntreprise($user);
    }
}
