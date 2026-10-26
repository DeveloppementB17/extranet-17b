<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Construit les étapes du tuto guidé selon le rôle métier de l’utilisateur.
 *
 * @phpstan-type TourStep array{element: string, title: string, description: string, side?: string}
 */
final class OnboardingTourBuilder
{
    /**
     * @return array{role: string, role_label: string, auto_start: bool, steps: list<TourStep>}
     */
    public function build(User $user): array
    {
        $role = $this->primaryRole($user);

        return [
            'role' => $role,
            'role_label' => $this->roleLabel($role),
            'auto_start' => !$user->hasCompletedOnboardingTour(),
            'steps' => $this->stepsForRole($role),
        ];
    }

    private function primaryRole(User $user): string
    {
        if ($user->is17bAdmin()) {
            return 'admin_17b';
        }
        if ($user->is17bUser()) {
            return 'user_17b';
        }
        if ($user->isCustomerAdmin()) {
            return 'admin_client';
        }

        return 'user_client';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'admin_17b' => 'Administrateur 17b',
            'user_17b' => 'Collaborateur 17b',
            'admin_client' => 'Administrateur client',
            default => 'Utilisateur client',
        };
    }

    /**
     * @return list<TourStep>
     */
    private function stepsForRole(string $role): array
    {
        $welcome = [
            'element' => '[data-tour="nav-home"]',
            'title' => 'Bienvenue sur l’extranet 17b',
            'description' => match ($role) {
                'admin_17b' => 'Petit tour guidé de votre espace d’administration et de pilotage clients.',
                'user_17b' => 'Petit tour guidé de votre espace de travail sur les clients qui vous sont attribués.',
                'admin_client' => 'Petit tour guidé de l’espace de votre entreprise : fichiers, stratégie et crédits temps.',
                default => 'Petit tour guidé pour retrouver fichiers, stratégie et crédits temps de votre entreprise.',
            },
            'side' => 'right',
        ];

        $files = [
            'element' => '[data-tour="nav-files"]',
            'title' => 'Fichiers',
            'description' => match ($role) {
                'admin_17b', 'user_17b' => 'Bibliothèque documentaire par client : dépôt, aperçu et téléchargement.',
                default => 'Consultez et téléchargez les documents partagés par 17b pour votre entreprise.',
            },
            'side' => 'right',
        ];

        $strategie = [
            'element' => '[data-tour="nav-strategie"]',
            'title' => 'Stratégie',
            'description' => match ($role) {
                'admin_17b', 'user_17b' => 'Documents de pilotage (plans, feuilles de route…) avec type, sujet et période.',
                default => 'Retrouvez les documents stratégiques de votre entreprise (plans, feuilles de route…).',
            },
            'side' => 'right',
        ];

        $credits = [
            'element' => '[data-tour="nav-credits"]',
            'title' => 'Crédits temps',
            'description' => match ($role) {
                'admin_17b', 'user_17b' => 'Enveloppes de minutes par client : création, interventions et suivi du solde.',
                default => 'Suivez le budget minutes de votre entreprise et le temps déjà consommé.',
            },
            'side' => 'right',
        ];

        $steps = [$welcome, $files, $strategie, $credits];

        if ($role === 'admin_client') {
            $steps[] = [
                'element' => '[data-tour="nav-users"]',
                'title' => 'Utilisateurs',
                'description' => 'Invitez ou retirez les collègues de votre entreprise qui peuvent se connecter à l’extranet.',
                'side' => 'right',
            ];
        }

        if ($role === 'admin_17b') {
            $steps[] = [
                'element' => '[data-tour="nav-admin"]',
                'title' => 'Administration',
                'description' => 'Gérez les entreprises, les comptes, les référentiels (types, sujets, catégories) et la présentation interne.',
                'side' => 'right',
            ];
        }

        if ($role === 'admin_17b' || $role === 'user_17b') {
            $steps[] = [
                'element' => '[data-tour="client-switcher"]',
                'title' => 'Entreprise active',
                'description' => $role === 'admin_17b'
                    ? 'Filtrez fichiers, stratégie et crédits : tous les clients, « Mes clients », ou un client précis. Vous pouvez aussi « voir comme le client ».'
                    : 'Sélectionnez le client sur lequel vous travaillez. La navigation se filtre alors sur cette entreprise.',
                'side' => 'top',
            ];
        }

        $steps[] = [
            'element' => '[data-tour="account"]',
            'title' => 'Mon compte',
            'description' => match ($role) {
                'admin_17b' => 'Profil, clients préférés (filtre Mes clients) et import CSV d’interventions.',
                'user_17b' => 'Modifiez votre profil et votre mot de passe.',
                'admin_client' => 'Gérez votre profil. Les utilisateurs de l’entreprise se gèrent dans le menu Utilisateurs.',
                default => 'Consultez votre profil et changez votre mot de passe si besoin.',
            },
            'side' => 'top',
        ];

        $steps[] = [
            'element' => '[data-tour="help-button"]',
            'title' => 'Relancer le guide',
            'description' => 'Ce bouton reste disponible : cliquez dessus à tout moment pour revoir ce tutoriel.',
            'side' => 'left',
        ];

        return $steps;
    }
}
