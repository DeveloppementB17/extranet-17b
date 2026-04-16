<?php

namespace App\EventSubscriber;

use App\Doctrine\Filter\EntrepriseFilter;
use App\Tenant\EntrepriseContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class EntrepriseFilterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EntrepriseContext $entrepriseContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $filters = $this->entityManager->getFilters();
        if (!$filters->has('entreprise')) {
            return;
        }

        $entreprise = $this->entrepriseContext->getCurrentEntreprise();
        if ($entreprise === null || $entreprise->getId() === null) {
            // Pas de contexte tenant (ex: pages publiques, login, fixtures),
            // on désactive pour éviter les erreurs et un filtre mal-paramétré.
            if ($filters->isEnabled('entreprise')) {
                $filters->disable('entreprise');
            }

            return;
        }

        if (!$filters->isEnabled('entreprise')) {
            $filters->enable('entreprise');
        }

        $filters->getFilter('entreprise')->setParameter(EntrepriseFilter::PARAM_NAME, (string) $entreprise->getId());
    }
}

