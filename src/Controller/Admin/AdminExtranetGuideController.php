<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_17B_ADMIN')]
final class AdminExtranetGuideController extends AbstractController
{
    #[Route('/admin/presentation', name: 'admin_extranet_presentation', methods: ['GET'])]
    public function presentation(
        #[Autowire(param: 'recette.enabled')]
        bool $recetteEnabled,
    ): Response {
        return $this->render('admin/extranet_guide.html.twig', [
            'recette_enabled' => $recetteEnabled,
            'role_labels' => $this->roleLabels(),
            'role_profiles' => $this->roleProfiles(),
            'modules' => $this->modules(),
            'extranet_rights' => $this->extranetRightsMatrix(),
        ]);
    }

    /** Ancienne URL — redirection vers la présentation extranet. */
    #[Route('/admin/guide-recette', name: 'admin_recette_guide', methods: ['GET'])]
    public function legacyGuideRecette(): RedirectResponse
    {
        return $this->redirectToRoute('admin_extranet_presentation', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function roleLabels(): array
    {
        return [
            ['key' => 'admin_17b', 'label' => 'Administrateur 17b'],
            ['key' => 'user_17b', 'label' => 'Utilisateur 17b'],
            ['key' => 'admin_client', 'label' => 'Administrateur client'],
            ['key' => 'user_client', 'label' => 'Utilisateur client'],
        ];
    }

    /**
     * @return list<array{role: string, label: string, audience: string, entreprise: string, resume: string, test_account: string}>
     */
    private function roleProfiles(): array
    {
        return [
            [
                'role' => 'ROLE_17B_ADMIN',
                'label' => 'Administrateur 17b',
                'audience' => 'Équipe 17b — configuration globale',
                'entreprise' => 'Rattaché à l’agence « 17b »',
                'resume' => 'Accès à tout : toutes les entreprises clientes, administration, dépôt de documents, gestion des crédits temps et des comptes.',
                'test_account' => 'admin-test@17b.test',
            ],
            [
                'role' => 'ROLE_17B_USER',
                'label' => 'Utilisateur 17b',
                'audience' => 'Équipe 17b — périmètre limité',
                'entreprise' => 'Agence « 17b » + entreprises clientes cochées',
                'resume' => 'Travaille uniquement sur les clients qui lui sont attribués. Doit sélectionner une « entreprise active » pour filtrer documents et crédits.',
                'test_account' => 'staff-partial@17b.test (Cliente Nord)',
            ],
            [
                'role' => 'ROLE_CUSTOMER_ADMIN',
                'label' => 'Administrateur client',
                'audience' => 'Référent côté client',
                'entreprise' => 'Une entreprise cliente (ex. Cliente Nord)',
                'resume' => 'Consulte documents et crédits temps de son organisation, gère les comptes collègues (utilisateurs client). Pas d’accès admin 17b.',
                'test_account' => 'admin-nord@clients.test',
            ],
            [
                'role' => 'ROLE_CUSTOMER_USER',
                'label' => 'Utilisateur client',
                'audience' => 'Collaborateur client',
                'entreprise' => 'Une entreprise cliente',
                'resume' => 'Consultation des fichiers et suivi des crédits temps en lecture seule. Pas de gestion d’utilisateurs ni de dépôt de documents.',
                'test_account' => 'user-nord@clients.test',
            ],
        ];
    }

    /**
     * @return list<array{title: string, path: string, description: string, roles: string}>
     */
    private function modules(): array
    {
        return [
            [
                'title' => 'Tableau de bord',
                'path' => '/',
                'description' => 'Vue d’ensemble après connexion : accès rapide aux documents et crédits temps, indicateurs selon le profil. Pour l’équipe 17b sans client sélectionné, des messages guident la sélection d’entreprise.',
                'roles' => 'Tous les rôles connectés',
            ],
            [
                'title' => 'Fichiers (documents)',
                'path' => '/documents',
                'description' => 'Bibliothèque organisée en dossiers (catégories). Prévisualisation et téléchargement. L’équipe 17b peut déposer des fichiers ou des liens externes (max. 20 Mo par fichier), modifier et supprimer.',
                'roles' => 'Lecture : tous · Gestion : 17b (admin & user autorisé)',
            ],
            [
                'title' => 'Crédits temps',
                'path' => '/credits-temps',
                'description' => 'Suivi des enveloppes de temps (minutes) par entreprise, historique des interventions, soldes restants. L’équipe 17b crée les crédits et saisit les consommations ; les clients consultent.',
                'roles' => 'Gestion : 17b · Lecture : clients',
            ],
            [
                'title' => 'Stratégie',
                'path' => '—',
                'description' => 'Espace réservé à venir (menu grisé dans la navigation).',
                'roles' => 'Non disponible',
            ],
            [
                'title' => 'Utilisateurs (côté client)',
                'path' => '/compte/utilisateurs',
                'description' => 'L’administrateur client invite ou retire des collègues de son entreprise (email, mot de passe).',
                'roles' => 'Administrateur client uniquement',
            ],
            [
                'title' => 'Mon compte',
                'path' => '/compte',
                'description' => 'Informations du compte connecté, lien de déconnexion.',
                'roles' => 'Tous',
            ],
            [
                'title' => 'Administration 17b',
                'path' => '/admin',
                'description' => 'Gestion des entreprises (agence + clients), de tous les utilisateurs et des paramètres avancés (catégories documents, catégories crédits).',
                'roles' => 'Administrateur 17b uniquement',
            ],
            [
                'title' => 'Entreprise active (équipe 17b)',
                'path' => 'Sidebar',
                'description' => 'Sélecteur en bas du menu latéral : filtre documents et crédits sur le client choisi. Badge visible en haut de page.',
                'roles' => 'Administrateur & utilisateur 17b',
            ],
        ];
    }

    /**
     * @return list<array{feature: string, detail: string, admin_17b: string, user_17b: string, admin_client: string, user_client: string}>
     */
    private function extranetRightsMatrix(): array
    {
        return [
            [
                'feature' => 'Tableau de bord',
                'detail' => 'Accueil, cartes documents et crédits temps.',
                'admin_17b' => 'Oui — toutes les entreprises',
                'user_17b' => 'Oui — filtré par client actif',
                'admin_client' => 'Oui — son entreprise',
                'user_client' => 'Oui — son entreprise',
            ],
            [
                'feature' => 'Documents — lecture & téléchargement',
                'detail' => 'Arborescence /documents, aperçu et téléchargement.',
                'admin_17b' => 'Oui — toutes les entreprises clientes',
                'user_17b' => 'Oui — entreprises gérées + client sélectionné',
                'admin_client' => 'Oui — son entreprise uniquement',
                'user_client' => 'Oui — son entreprise uniquement',
            ],
            [
                'feature' => 'Documents — dépôt & gestion',
                'detail' => 'Ajout en lot, modification, suppression.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui — si entreprises gérées / client actif',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Catégories de documents',
                'detail' => 'CRUD dossiers /documents/categories.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Non',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Crédits temps — consultation',
                'detail' => 'Liste et fiche détail /credits-temps.',
                'admin_17b' => 'Oui — toutes les entreprises',
                'user_17b' => 'Oui — client actif uniquement',
                'admin_client' => 'Lecture seule — son entreprise',
                'user_client' => 'Lecture seule — son entreprise',
            ],
            [
                'feature' => 'Crédits temps — gestion',
                'detail' => 'Création, interventions, modification, archivage.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui — client actif + périmètre géré',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Crédits temps — suppression',
                'detail' => 'Suppression d’un crédit non consommé.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Non',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Catégories crédits temps',
                'detail' => 'Administration /admin/credits/categories.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Non',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Utilisateurs de l’entreprise',
                'detail' => 'Gestion des collègues client /compte/utilisateurs.',
                'admin_17b' => 'Via admin 17b (tous comptes)',
                'user_17b' => 'Non',
                'admin_client' => 'Oui — son entreprise',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Administration 17b',
                'detail' => 'Entreprises, utilisateurs tous rôles, /admin.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Non',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Sélecteur « entreprise active »',
                'detail' => 'Select2 dans la barre latérale (équipe 17b).',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui',
                'admin_client' => 'Non',
                'user_client' => 'Non',
            ],
            [
                'feature' => 'Connexion & sécurité',
                'detail' => 'Mot de passe, code email, mot de passe oublié.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui',
                'admin_client' => 'Oui',
                'user_client' => 'Oui',
            ],
        ];
    }
}
