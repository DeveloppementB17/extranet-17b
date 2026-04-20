<?php

namespace App\Tenant;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class ManagedClientContext
{
    private const SESSION_KEY = 'staff_selected_client_id';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntrepriseRepository $entrepriseRepository,
    ) {
    }

    public function getSelectedManagedEntreprise(User $actor): ?Entreprise
    {
        if (!$actor->is17bStaff()) {
            return null;
        }

        $session = $this->requestStack->getSession();
        if (!$session->has(self::SESSION_KEY)) {
            return null;
        }

        $selectedId = (int) $session->get(self::SESSION_KEY);
        if ($selectedId <= 0 || !\in_array($selectedId, $actor->getManagedEntrepriseIds(), true)) {
            $session->remove(self::SESSION_KEY);

            return null;
        }

        $entreprise = $this->entrepriseRepository->find($selectedId);
        if (!$entreprise instanceof Entreprise || $entreprise->isAgency()) {
            $session->remove(self::SESSION_KEY);

            return null;
        }

        return $entreprise;
    }

    public function setSelectedManagedEntreprise(User $actor, Entreprise $entreprise): void
    {
        if (!$actor->is17bStaff() || !$actor->managesEntreprise($entreprise) || $entreprise->isAgency()) {
            throw new \InvalidArgumentException('Entreprise non autorisée pour cet utilisateur 17b.');
        }

        $this->requestStack->getSession()->set(self::SESSION_KEY, $entreprise->getId());
    }
}
