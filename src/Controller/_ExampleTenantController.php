<?php

namespace App\Controller;

use App\Security\Voter\EntrepriseOwnedVoter;
use App\Tenant\EntrepriseOwnedInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exemple minimal d'usage des Voters multi-tenant.
 *
 * À supprimer quand les premiers controllers métier sont créés.
 */
final class _ExampleTenantController extends AbstractController
{
    #[Route('/_example/tenant-check', name: 'example_tenant_check')]
    public function __invoke(): Response
    {
        /** @var EntrepriseOwnedInterface|null $someEntity */
        $someEntity = null;

        if ($someEntity !== null) {
            $this->denyAccessUnlessGranted(EntrepriseOwnedVoter::VIEW, $someEntity);
        }

        return new Response('OK');
    }
}

