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
            'simple_steps' => $this->simpleSteps(),
            'simple_roles' => $this->simpleRoles(),
            'simple_pillars' => $this->simplePillars(),
            'key_features' => $this->keyFeatures(),
        ]);
    }

    /** Ancienne URL — redirection vers la présentation extranet. */
    #[Route('/admin/guide-recette', name: 'admin_recette_guide', methods: ['GET'])]
    public function legacyGuideRecette(): RedirectResponse
    {
        return $this->redirectToRoute('admin_extranet_presentation', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    /**
     * @return list<array{number: string, title: string, text: string}>
     */
    private function simpleSteps(): array
    {
        return [
            [
                'number' => '1',
                'title' => 'On se connecte',
                'text' => 'Chacun ouvre l’extranet avec son email et son mot de passe. C’est comme une porte d’entrée personnelle.',
            ],
            [
                'number' => '2',
                'title' => 'On voit son espace',
                'text' => 'Le menu à gauche mène vers l’accueil, les fichiers et les crédits temps. On ne voit que ce qui concerne notre entreprise (ou nos clients, pour l’équipe 17b).',
            ],
            [
                'number' => '3',
                'title' => 'On échange et on suit',
                'text' => '17b dépose des documents et note le temps passé. Le client consulte, télécharge et suit son solde de minutes.',
            ],
            [
                'number' => '4',
                'title' => 'Chacun son rôle',
                'text' => 'Tout le monde n’a pas les mêmes boutons : certains peuvent ajouter des fichiers, d’autres seulement les lire. C’est normal et voulu.',
            ],
        ];
    }

    /**
     * @return list<array{emoji: string, title: string, subtitle: string, can_do: list<string>, cannot_do: list<string>}>
     */
    private function simpleRoles(): array
    {
        return [
            [
                'emoji' => '🛠️',
                'title' => 'Admin 17b',
                'subtitle' => 'Pilotage complet',
                'can_do' => ['Tout voir', 'Gérer entreprises & comptes', 'Déposer des fichiers', 'Gérer les crédits temps'],
                'cannot_do' => ['Rien de bloquant — c’est le profil le plus ouvert'],
            ],
            [
                'emoji' => '👤',
                'title' => 'Collaborateur 17b',
                'subtitle' => 'Sur ses clients uniquement',
                'can_do' => ['Choisir une entreprise active', 'Travailler sur ses clients assignés', 'Déposer des fichiers', 'Saisir du temps'],
                'cannot_do' => ['Accéder à l’admin globale', 'Voir les clients non assignés'],
            ],
            [
                'emoji' => '⭐',
                'title' => 'Admin client',
                'subtitle' => 'Référent chez le client',
                'can_do' => ['Voir les fichiers de son entreprise', 'Suivre les crédits temps', 'Inviter des collègues'],
                'cannot_do' => ['Déposer des fichiers', 'Modifier les crédits', 'Accéder à /admin'],
            ],
            [
                'emoji' => '👁️',
                'title' => 'Utilisateur client',
                'subtitle' => 'Consultation',
                'can_do' => ['Télécharger les fichiers', 'Consulter les crédits temps', 'Gérer son compte'],
                'cannot_do' => ['Ajouter des fichiers', 'Gérer d’autres utilisateurs', 'Modifier quoi que ce soit côté 17b'],
            ],
        ];
    }

    /**
     * Fonctionnalités concrètes à mettre en avant (guide simple + démo).
     *
     * @return list<array{emoji: string, group: string, color: string, items: list<array{title: string, text: string}>}>
     */
    private function keyFeatures(): array
    {
        return [
            [
                'emoji' => '📁',
                'group' => 'Fichiers',
                'color' => 'border-sky-300 bg-sky-50/60',
                'items' => [
                    [
                        'title' => 'Upload multiple',
                        'text' => 'Déposer plusieurs fichiers en une seule fois (même entreprise, même dossier). Chaque fichier apparaît avec un titre distinct.',
                    ],
                    [
                        'title' => 'Lien externe',
                        'text' => 'Alternative au fichier : enregistrer un document comme lien https (Google Drive, WeTransfer, etc.) sans upload sur le serveur.',
                    ],
                    [
                        'title' => 'Arborescence',
                        'text' => 'Documents rangés par catégories / sous-dossiers. Prévisualisation et téléchargement selon le type de fichier.',
                    ],
                ],
            ],
            [
                'emoji' => '⏱️',
                'group' => 'Crédits temps',
                'color' => 'border-amber-300 bg-amber-50/60',
                'items' => [
                    [
                        'title' => 'Plusieurs enveloppes en parallèle',
                        'text' => 'Une entreprise peut avoir plusieurs crédits actifs (ex. « Site web », « Print »). Tous sont visibles dans le même tableau.',
                    ],
                    [
                        'title' => 'Recherche, filtres et tri',
                        'text' => 'Filtrer par entreprise, catégorie, statut (actif / archivé), rechercher par titre ou n° de dossier, trier les colonnes.',
                    ],
                    [
                        'title' => 'Intervention rapide',
                        'text' => 'Depuis la liste, formulaire intégré pour saisir une intervention : choix du crédit concerné si plusieurs sont actifs, sans ouvrir chaque fiche.',
                    ],
                ],
            ],
            [
                'emoji' => '🔐',
                'group' => 'Transversal',
                'color' => 'border-violet-300 bg-violet-50/60',
                'items' => [
                    [
                        'title' => 'Entreprise active (staff 17b)',
                        'text' => 'Sélecteur en bas du menu : filtre fichiers et crédits sur le client choisi. Badge visible en haut de page.',
                    ],
                    [
                        'title' => 'Périmètre isolé',
                        'text' => 'Chaque client ne voit que ses données. Un collaborateur 17b ne voit que les entreprises qui lui sont assignées.',
                    ],
                    [
                        'title' => 'Connexion flexible',
                        'text' => 'Mot de passe classique ou code à usage unique par email. Réinitialisation du mot de passe oublié.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array{emoji: string, title: string, text: string, color: string}>
     */
    private function simplePillars(): array
    {
        return [
            [
                'emoji' => '📁',
                'title' => 'Fichiers',
                'text' => 'Les livrables et documents partagés, rangés dans des dossiers.',
                'color' => 'bg-sky-50 border-sky-300',
            ],
            [
                'emoji' => '⏱️',
                'title' => 'Crédits temps',
                'text' => 'Le « budget minutes » de l’entreprise et ce qu’il reste après chaque intervention.',
                'color' => 'bg-amber-50 border-amber-300',
            ],
            [
                'emoji' => '👥',
                'title' => 'Comptes',
                'text' => 'Qui peut se connecter : équipe 17b d’un côté, équipe client de l’autre.',
                'color' => 'bg-violet-50 border-violet-300',
            ],
        ];
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
                'description' => 'Bibliothèque en arborescence (catégories). Prévisualisation et téléchargement. Dépôt via /documents/ajouter : plusieurs fichiers d’un coup ou un lien https seul (max. 20 Mo par fichier, pas les deux à la fois). Modification et suppression réservées à l’équipe 17b.',
                'roles' => 'Lecture : tous · Gestion : 17b (admin & user autorisé)',
            ],
            [
                'title' => 'Crédits temps',
                'path' => '/credits-temps',
                'description' => 'Plusieurs enveloppes minutes peuvent coexister par client. Liste avec recherche, filtres et tri ; formulaire d’intervention rapide depuis la page. Fiche détail : solde, historique. L’équipe 17b crée et consomme ; les clients consultent uniquement.',
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
                'feature' => 'Documents — upload multiple ou lien',
                'detail' => 'Plusieurs fichiers en une fois, ou URL https seule (exclusif).',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui — client actif',
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
                'feature' => 'Crédits temps — plusieurs enveloppes & intervention rapide',
                'detail' => 'Liste multi-crédits, filtres, widget intervention depuis /credits-temps.',
                'admin_17b' => 'Oui',
                'user_17b' => 'Oui — client actif',
                'admin_client' => 'Consultation seule',
                'user_client' => 'Consultation seule',
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
