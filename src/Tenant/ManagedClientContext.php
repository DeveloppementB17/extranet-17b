<?php

namespace App\Tenant;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\EntrepriseRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class ManagedClientContext
{
    public const SWITCHER_VALUE_MINE = '__mine__';

    private const SESSION_KEY = 'staff_selected_client_id';
    private const SESSION_SCOPE_KEY = 'staff_client_scope';
    private const SCOPE_MINE = 'mine';

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

        if ($this->isMineScope($actor)) {
            return null;
        }

        $session = $this->requestStack->getSession();
        if (!$session->has(self::SESSION_KEY)) {
            return null;
        }

        $selectedId = (int) $session->get(self::SESSION_KEY);
        if ($selectedId <= 0) {
            $session->remove(self::SESSION_KEY);

            return null;
        }

        $entreprise = $this->entrepriseRepository->find($selectedId);
        if (!$entreprise instanceof Entreprise || !$actor->managesEntreprise($entreprise)) {
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

        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_SCOPE_KEY);
        $session->set(self::SESSION_KEY, $entreprise->getId());
    }

    public function clearSelectedManagedEntreprise(User $actor): void
    {
        if (!$actor->is17bStaff()) {
            return;
        }

        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
        $session->remove(self::SESSION_SCOPE_KEY);
    }

    /**
     * Scope « mes clients » : réservé aux admins 17b (préférences managedEntreprises).
     */
    public function isMineScope(User $actor): bool
    {
        if (!$actor->is17bAdmin()) {
            return false;
        }

        return $this->requestStack->getSession()->get(self::SESSION_SCOPE_KEY) === self::SCOPE_MINE;
    }

    public function setMineScope(User $actor): void
    {
        if (!$actor->is17bAdmin()) {
            throw new \InvalidArgumentException('Le filtre « mes clients » est réservé aux administrateurs 17b.');
        }

        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
        $session->set(self::SESSION_SCOPE_KEY, self::SCOPE_MINE);
    }

    /**
     * IDs d’entreprises à forcer sur les listes (documents, crédits…).
     * null = pas de filtre multi-ids (tout le monde ou un seul client via getSelectedManagedEntreprise).
     *
     * @return list<int>|null
     */
    public function getForcedEntrepriseIds(User $actor): ?array
    {
        if (!$this->isMineScope($actor)) {
            return null;
        }

        return $actor->getManagedEntrepriseIds();
    }
}
