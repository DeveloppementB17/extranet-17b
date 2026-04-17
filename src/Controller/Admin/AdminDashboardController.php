<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_17B_ADMIN')]
final class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }
}
