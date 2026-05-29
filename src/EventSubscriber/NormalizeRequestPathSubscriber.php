<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Corrige les URLs avec doubles slash (ex. //admin/…) qui ne matchent pas le routeur Symfony.
 */
final class NormalizeRequestPathSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 512],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_contains($path, '//')) {
            return;
        }

        $normalized = (string) preg_replace('#/+#', '/', $path);
        if ($normalized === '' || $normalized === $path) {
            return;
        }

        $query = $request->getQueryString();
        $target = $normalized.($query !== null && $query !== '' ? '?'.$query : '');

        $event->setResponse(new RedirectResponse($target, RedirectResponse::HTTP_MOVED_PERMANENTLY));
    }
}
