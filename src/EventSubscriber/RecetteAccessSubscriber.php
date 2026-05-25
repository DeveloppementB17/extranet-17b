<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Coupe l’outil /recette lorsque RECETTE_ENABLED=0 (désactivation post-recettage sans déploiement de code).
 */
final class RecetteAccessSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(param: 'recette.enabled')]
        private readonly bool $recetteEnabled,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->recetteEnabled) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/recette')) {
            throw new NotFoundHttpException();
        }
    }
}
